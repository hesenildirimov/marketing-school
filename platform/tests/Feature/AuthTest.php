<?php

namespace Tests\Feature;

use App\Domain\Auth\TokenService;
use App\Models\AppNotification;
use App\Models\AuthToken;
use App\Models\User;
use App\Models\UserSession;
use App\Support\System;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function register(array $over = [])
    {
        return $this->post('/platform/register', array_merge([
            'name' => 'Aysel Məmmədova', 'email' => 'aysel@example.com', 'phone' => '050 123 45 67', 'consent' => '1',
        ], $over));
    }

    /** Test üçün: e-maildəki linkdən xam tokeni almaq mümkün deyil (yalnız hash saxlanılır), ona görə yeni token buraxırıq. */
    private function freshToken(User $u, string $purpose = TokenService::VERIFY): string
    {
        return System::run(fn () => app(TokenService::class)->issue($u, $purpose, now()->addHour()));
    }

    public function test_registration_creates_unverified_user_and_queues_link_with_top_priority(): void
    {
        $this->register()->assertRedirect(route('check-email'));
        $u = User::where('email', 'aysel@example.com')->firstOrFail();
        $this->assertNull($u->email_verified_at);
        $this->assertNull($u->password);
        $this->assertSame('+994501234567', $u->phone_e164);
        $n = System::run(fn () => AppNotification::where('user_id', $u->id)->where('channel', 'email')->firstOrFail());
        $this->assertSame('verify_email', $n->event_type);
        $this->assertSame(0, $n->priority);
        $this->assertArrayNotHasKey('url', $n->payload, 'tokenli link outbox-da açıq saxlanılmır');
        $url = Crypt::decryptString($n->payload['url_enc']);
        $this->assertStringContainsString('/platform/verify?t=', $url);
        // DB-də yalnız hash: linkdəki token bazada açıq şəkildə yoxdur
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertStringNotContainsString($q['t'], json_encode(DB::connection('pgsql_owner')->table('notifications')->get()));
        $this->assertFalse(AuthToken::where('token_hash', $q['t'])->exists());
        $this->assertTrue(AuthToken::where('token_hash', hash('sha256', $q['t']))->exists());
    }

    public function test_duplicate_registration_gives_same_response_and_does_not_reveal_account(): void
    {
        $this->register()->assertRedirect(route('check-email'));
        $this->register(['email' => 'AYSEL@example.com'])->assertRedirect(route('check-email'));
        $this->assertSame(1, User::count());
        $events = System::run(fn () => AppNotification::where('channel', 'email')->pluck('event_type')->all());
        $this->assertEqualsCanonicalizing(['verify_email', 'account_exists'], $events);
    }

    public function test_invalid_phone_and_honeypot_rejected(): void
    {
        $this->register(['phone' => '123'])->assertSessionHasErrors('phone');
        $this->register(['website' => 'spam'])->assertSessionHasErrors('website');
        $this->assertSame(0, User::count());
    }

    public function test_verify_get_does_not_consume_and_post_sets_password_once(): void
    {
        $this->register();
        $u = User::firstOrFail();
        $t = $this->freshToken($u);
        $this->get('/platform/verify?t='.$t)->assertOk()->assertSee('name="password"', false);
        $this->get('/platform/verify?t='.$t)->assertOk()->assertSee('name="password"', false);
        $this->post('/platform/verify', ['t' => $t, 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'])
            ->assertRedirect(route('dashboard'));
        $this->assertNotNull($u->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($u);
        // Təkrar istifadə bloklanır
        auth()->logout();
        $this->post('/platform/verify', ['t' => $t, 'password' => 'Other12345x', 'password_confirmation' => 'Other12345x'])
            ->assertRedirect(route('verify.show'));
        $this->assertTrue(\Hash::check('NewPass12345', $u->fresh()->password));
    }

    public function test_new_link_invalidates_old_one(): void
    {
        $u = $this->makeUser(verified: false);
        $old = $this->freshToken($u);
        $new = $this->freshToken($u);
        $this->get('/platform/verify?t='.$old)->assertSee(__('ui.link_invalid'));
        $this->get('/platform/verify?t='.$new)->assertSee('name="password"', false);
    }

    public function test_expired_link_rejected(): void
    {
        $u = $this->makeUser(verified: false);
        $t = $this->freshToken($u);
        $this->travel(2)->hours();
        $this->post('/platform/verify', ['t' => $t, 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'])
            ->assertRedirect(route('verify.show'));
        $this->assertGuest();
    }

    public function test_login_errors_do_not_reveal_whether_account_exists(): void
    {
        $this->makeUser(['email' => 'real@example.com']);
        $a = $this->post('/platform/login', ['email' => 'real@example.com', 'password' => 'wrong-pass'])->assertSessionHasErrors('email');
        $msgA = session('errors')->first('email');
        $this->post('/platform/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass'])->assertSessionHasErrors('email');
        $this->assertSame($msgA, session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_unverified_user_cannot_log_in(): void
    {
        $this->makeUser(['email' => 'unv@example.com'], verified: false);
        $this->post('/platform/login', ['email' => 'unv@example.com', 'password' => 'Secret12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_rotates_session_and_creates_server_session(): void
    {
        $u = $this->makeUser(['email' => 'rot@example.com']);
        $this->get('/platform/login');
        $before = session()->getId();
        $this->post('/platform/login', ['email' => 'rot@example.com', 'password' => 'Secret12345'])->assertRedirect(route('dashboard'));
        $this->assertNotSame($before, session()->getId());
        $this->assertSame(1, System::run(fn () => UserSession::where('user_id', $u->id)->whereNull('revoked_at')->count()));
    }

    public function test_session_cookie_flags(): void
    {
        config(['session.secure' => true]);
        $this->makeUser(['email' => 'ck@example.com']);
        $res = $this->post('/platform/login', ['email' => 'ck@example.com', 'password' => 'Secret12345']);
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === '__Secure-ms_session');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/platform', $cookie->getPath());
    }

    public function test_logout_revokes_server_session(): void
    {
        $u = $this->makeUser();
        $this->signIn($u)->get('/platform/dashboard')->assertOk();
        $this->post('/platform/logout')->assertRedirect(route('login'));
        $this->assertSame(0, System::run(fn () => UserSession::where('user_id', $u->id)->whereNull('revoked_at')->count()));
    }

    public function test_revoked_session_is_rejected(): void
    {
        $u = $this->makeUser();
        $this->signIn($u)->get('/platform/dashboard')->assertOk();
        System::run(fn () => UserSession::where('user_id', $u->id)->update(['revoked_at' => now()]));
        $this->get('/platform/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_concurrent_session_limit_revokes_oldest(): void
    {
        $u = $this->makeUser(['email' => 'lim@example.com']);
        foreach (range(1, 3) as $i) {
            $this->travel(1)->minutes();
            $this->post('/platform/login', ['email' => 'lim@example.com', 'password' => 'Secret12345']);
            auth()->logout();
            $this->flushSession();
        }
        $rows = System::run(fn () => UserSession::where('user_id', $u->id)->orderBy('created_at')->get());
        $this->assertCount(3, $rows);
        $this->assertSame('device_limit', $rows[0]->revoke_reason);
        $this->assertNull($rows[1]->revoked_at);
        $this->assertNull($rows[2]->revoked_at);
    }

    public function test_password_reset_is_enumeration_safe_single_use_and_revokes_sessions(): void
    {
        $u = $this->makeUser(['email' => 'pr@example.com']);
        $this->signIn($u)->get('/platform/dashboard')->assertOk();
        auth()->logout();
        $r1 = $this->post('/platform/password/forgot', ['email' => 'pr@example.com']);
        $r2 = $this->post('/platform/password/forgot', ['email' => 'ghost@example.com']);
        $this->assertSame(session('status'), __('ui.reset_sent'));
        $r1->assertSessionHas('status', __('ui.reset_sent'));
        $r2->assertSessionHas('status', __('ui.reset_sent'));

        $t = $this->freshToken($u, TokenService::RESET);
        $this->post('/platform/password/reset', ['t' => $t, 'password' => 'Changed12345', 'password_confirmation' => 'Changed12345'])
            ->assertRedirect(route('login'));
        $this->assertSame(0, System::run(fn () => UserSession::where('user_id', $u->id)->whereNull('revoked_at')->count()));
        $this->post('/platform/password/reset', ['t' => $t, 'password' => 'Again123456', 'password_confirmation' => 'Again123456'])
            ->assertSessionHasErrors('t');
        $this->assertTrue(\Hash::check('Changed12345', $u->fresh()->password));
    }

    public function test_login_rate_limited(): void
    {
        $this->makeUser(['email' => 'rl@example.com']);
        foreach (range(1, 5) as $i) {
            $this->post('/platform/login', ['email' => 'rl@example.com', 'password' => 'bad'])->assertStatus(302);
        }
        $this->post('/platform/login', ['email' => 'rl@example.com', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_password_hash_is_argon2id(): void
    {
        $u = $this->makeUser();
        $this->assertStringStartsWith('$argon2id$', $u->password);
    }
}
