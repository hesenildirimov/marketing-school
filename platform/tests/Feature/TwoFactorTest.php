<?php

namespace Tests\Feature;

use App\Domain\Auth\TwoFactor;
use App\Models\TwoFactorSecret;
use App\Models\UserSession;
use App\Support\System;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_admin_must_set_up_2fa_before_reaching_admin(): void
    {
        $admin = $this->makeUser(['email' => 'adm@example.com'], ['admin']);
        $this->post('/platform/login', ['email' => 'adm@example.com', 'password' => 'Secret12345'])->assertRedirect(route('2fa.challenge'));
        $this->get('/platform/admin')->assertRedirect(route('2fa.challenge'));
        $this->get('/platform/dashboard')->assertRedirect(route('2fa.challenge'));
        $this->getJson('/platform/admin')->assertStatus(403);

        $this->get('/platform/2fa')->assertOk()->assertSee('<svg', false);
        $secret = TwoFactorSecret::findOrFail($admin->id)->secret_encrypted;
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/platform/2fa', ['code' => $code])->assertOk()->assertSee(__('ui.recovery_title'));
        $this->get('/platform/admin')->assertOk();
        $this->assertSame(10, \DB::table('recovery_codes')->where('user_id', $admin->id)->count());
    }

    public function test_totp_replay_and_wrong_code_rejected(): void
    {
        $admin = $this->makeUser(['email' => 'adm2@example.com'], ['instructor']);
        $tf = app(TwoFactor::class);
        $secret = $tf->begin($admin);
        $tf->confirm($admin);
        $code = (new Google2FA)->getCurrentOtp($secret->secret_encrypted);
        $this->assertTrue($tf->verify($admin, $code));
        $this->assertFalse($tf->verify($admin, $code), 'eyni kod ikinci dəfə qəbul olunmamalıdır');
        $this->assertFalse($tf->verify($admin, '000000'));
    }

    public function test_recovery_code_is_single_use_and_stored_hashed(): void
    {
        $admin = $this->makeUser(['email' => 'adm3@example.com'], ['admin']);
        $tf = app(TwoFactor::class);
        $tf->begin($admin);
        $codes = $tf->confirm($admin);
        $this->assertFalse(\DB::table('recovery_codes')->where('code_hash', $codes[0])->exists());
        $this->signIn($admin, twoFactor: false);
        $this->post('/platform/2fa', ['recovery' => $codes[0]])->assertRedirect();
        $this->assertNotNull(System::run(fn () => UserSession::where('user_id', $admin->id)->value('two_factor_at')));
        $this->assertFalse($tf->useRecoveryCode($admin, $codes[0]));
    }

    public function test_student_does_not_need_2fa(): void
    {
        $s = $this->makeUser();
        $this->signIn($s, twoFactor: false)->get('/platform/dashboard')->assertOk();
        $this->get('/platform/admin')->assertStatus(403);
    }

    public function test_staff_session_expires_after_idle(): void
    {
        $admin = $this->makeUser([], ['admin']);
        $this->signIn($admin)->get('/platform/admin')->assertOk();
        $this->travel(config('platform.auth.staff_idle_minutes') + 1)->minutes();
        $this->get('/platform/admin')->assertRedirect(route('login'));
    }
}
