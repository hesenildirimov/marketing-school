<?php

namespace Tests\Feature;

use App\Domain\Auth\TokenService;
use App\Domain\Demo\DemoService;
use App\Domain\Notifications\NotificationSender;
use App\Mail\PlatformMail;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\DemoClaim;
use App\Models\Lesson;
use App\Models\User;
use App\Support\DbContext;
use App\Support\System;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Concerns\BuildsCourses;
use Tests\Support\Parallel;
use Tests\TestCase;

/** Bölmə 7 — Demo testləri. */
class DemoTest extends TestCase
{
    use BuildsCourses;

    private Course $mk;

    private Course $dm;

    private array $L;

    protected function setUp(): void
    {
        parent::setUp();
        ['course' => $this->mk, 'lessons' => $this->L] = $this->buildCourse('marketinq-kursu', modules: 4, lessonsPer: 2);
        ['course' => $this->dm] = $this->buildCourse('digital-marketinq-kursu', modules: 3, lessonsPer: 1);
    }

    private function form(array $over = []): array
    {
        return array_merge(['ad' => 'Nigar Əliyeva', 'email' => 'nigar.aliyeva@gmail.com', 'tel' => '050 123 45 67',
            'kurs' => 'marketinq-kursu', 'lang' => 'az', 'consent' => '1', 'ts' => (string) ((time() - 30) * 1000)], $over);
    }

    private function submit(array $over = [], array $headers = ['Sec-Fetch-Site' => 'same-origin'])
    {
        return $this->withHeaders($headers)->post('/platform/api/demo/register', $this->form($over));
    }

    private function claims()
    {
        return System::run(fn () => DemoClaim::orderBy('id')->get());
    }

    private function demoUser(): User
    {
        return User::where('email', 'nigar.aliyeva@gmail.com')->firstOrFail();
    }

    private function students(): int
    {
        return User::where('email', 'not like', 'pub-%')->count();
    }

    private function verifyAs(User $u): void
    {
        $t = System::run(fn () => app(TokenService::class)->issue($u, TokenService::VERIFY, now()->addDay()));
        $this->post('/platform/verify', ['t' => $t, 'password' => 'DemoPass123', 'password_confirmation' => 'DemoPass123']);
    }

    public function test_new_user_registers_and_link_is_sent_immediately(): void
    {
        Mail::fake();
        $this->submit()->assertRedirect(route('check-email', ['lang' => 'az']));
        $u = User::where('email', 'nigar.aliyeva@gmail.com')->firstOrFail();
        $this->assertSame('+994501234567', $u->phone_e164);
        $this->assertCount(1, $this->claims());
        $n = System::run(fn () => AppNotification::where('channel', 'email')->where('event_type', 'demo_registered')->firstOrFail());
        $this->assertSame(0, $n->priority);
        app(NotificationSender::class)->run();
        Mail::assertSent(PlatformMail::class, fn ($m) => $m->event === 'demo_registered' && str_contains($m->data['url'], '/platform/verify?t='));
    }

    public function test_language_parameter_becomes_account_locale(): void
    {
        $this->submit(['lang' => 'en'])->assertRedirect(route('check-email', ['lang' => 'en']));
        $this->assertSame('en', $this->demoUser()->locale);
    }

    public function test_24h_counts_from_registration_not_from_email_click(): void
    {
        $this->submit();
        $registeredAt = now();
        $claim = $this->claims()->first();
        $this->assertEquals($registeredAt->copy()->addHours(24)->startOfSecond(), $claim->expires_at);
        $this->travel(20)->hours();
        $this->verifyAs($this->demoUser());
        $this->assertEquals($claim->expires_at, $this->claims()->first()->expires_at, 'gec açılan link müddəti uzatmır');
        $this->assertNotNull($this->claims()->first()->email_confirmed_at);
        $this->postJson("/platform/api/lessons/{$this->L[1][1]->id}/playback")->assertOk();
        $this->travelTo($this->claims()->first()->expires_at->copy()->addSecond());
        $this->postJson("/platform/api/lessons/{$this->L[1][1]->id}/playback")->assertStatus(403);
    }

    public function test_link_opened_after_demo_expired_confirms_email_and_shows_buy_screen(): void
    {
        $this->submit();
        $this->travel(25)->hours();
        $u = $this->demoUser();
        $t = System::run(fn () => app(TokenService::class)->issue($u, TokenService::VERIFY, now()->addDay()));
        $this->post('/platform/verify', ['t' => $t, 'password' => 'DemoPass123', 'password_confirmation' => 'DemoPass123'])
            ->assertRedirect(route('course.show', $this->mk));
        $this->assertNotNull($u->fresh()->email_verified_at);
        $this->get(route('course.show', $this->mk))->assertStatus(403)->assertSee(__('course.buy_demo_expired'));
    }

    public function test_resend_does_not_create_new_demo_or_extend_and_invalidates_old_link(): void
    {
        $this->submit();
        $u = $this->demoUser();
        $claim = $this->claims()->first();
        $old = System::run(fn () => app(TokenService::class)->issue($u, TokenService::VERIFY, now()->addDay()));
        $this->travel(2)->hours();
        $this->post('/platform/demo/resend', ['email' => 'NIGAR.ALIYEVA@gmail.com'])->assertSessionHas('status', __('demo.resend_sent'));
        $this->get('/platform/verify?t='.$old)->assertSee(__('ui.link_invalid'));
        $this->assertCount(1, $this->claims());
        $this->assertEquals($claim->expires_at, $this->claims()->first()->expires_at);
        // Resend limiti: 3/saat/hesab (sayğac token buraxılışı üzrə)
        foreach (range(1, 4) as $i) {
            $this->post('/platform/demo/resend', ['email' => 'nigar.aliyeva@gmail.com']);
        }
        $this->assertLessThanOrEqual(3, \DB::table('auth_tokens')->where('user_id', $u->id)->where('created_at', '>', now()->subHour())->count());
    }

    public function test_same_normalized_email_variants_cannot_get_second_demo(): void
    {
        $this->submit();
        foreach (['Nigar.Aliyeva+promo@gmail.com', 'n.i.g.a.r.aliyeva@googlemail.com', 'NIGARALIYEVA@GMAIL.COM'] as $i => $variant) {
            Cache::flush(); // e-mail üzrə rate limit (3/saat) bu testdə yoxlanılmır
            $this->submit(['email' => $variant, 'tel' => '055 000 00 0'.$i])->assertRedirect(route('check-email', ['lang' => 'az']));
        }
        $this->assertCount(1, $this->claims(), 'normallaşdırılmış e-mail eynidir — ikinci demo yoxdur');
        $events = System::run(fn () => AppNotification::where('channel', 'email')->pluck('event_type')->countBy()->all());
        $this->assertSame(1, $events['demo_registered']);
        $this->assertGreaterThanOrEqual(2, $events['demo_unavailable'] ?? 0);
    }

    public function test_same_phone_in_different_formats_cannot_get_second_demo(): void
    {
        $this->submit();
        foreach (['+994 50 123 45 67', '0 50 123 45 67', '994501234567', '(050) 123-45-67'] as $i => $p) {
            $this->submit(['email' => "other{$i}@mail.az", 'tel' => $p]);
        }
        $this->assertCount(1, $this->claims());
    }

    public function test_cannot_get_second_demo_by_switching_course(): void
    {
        $this->submit();
        $this->submit(['kurs' => 'digital-marketinq-kursu']);
        $this->assertCount(1, $this->claims());
        $this->assertSame($this->mk->id, $this->claims()->first()->course_id);
        // Daxil olmuş istifadəçi də digər kurs üçün demo başlada bilmir
        $u = $this->demoUser();
        $this->signIn($u)->post("/platform/demo/start/{$this->dm->slug}")->assertStatus(403);
    }

    public function test_parallel_registrations_with_same_email_create_one_demo(): void
    {
        $mk = $this->mk;
        $results = Parallel::run(5, fn () => app(DemoService::class)->register('Paralel', 'race@example.com', '+994551112233', $mk, 'az', '1.2.3.4'));
        sort($results);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === DemoService::OK)), 'yalnız biri uğurlu: '.json_encode($results));
        $this->assertSame([], array_filter($results, fn ($r) => str_starts_with((string) $r, 'ERR')), json_encode($results));
        $this->assertCount(1, $this->claims());
        $this->assertSame(1, User::where('email', 'race@example.com')->count());
    }

    public function test_parallel_registrations_with_email_variants_create_one_demo(): void
    {
        $mk = $this->mk;
        $emails = ['racer@gmail.com', 'r.acer@gmail.com', 'racer+1@gmail.com', 'RA.CER@gmail.com'];
        $results = Parallel::run(4, fn (int $i) => app(DemoService::class)->register('P', $emails[$i], '+99455000000'.$i, $mk, 'az', null));
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === DemoService::OK)), json_encode($results));
        $this->assertCount(1, $this->claims());
    }

    public function test_demo_gives_access_only_to_first_two_modules_of_chosen_course(): void
    {
        $this->submit();
        $u = $this->demoUser();
        $this->verifyAs($u);
        $this->postJson("/platform/api/lessons/{$this->L[1][1]->id}/playback")->assertOk();
        $this->postJson("/platform/api/lessons/{$this->L[2][2]->id}/playback")->assertOk();
        foreach ([$this->L[3][1], $this->L[4][2]] as $locked) {
            $this->postJson("/platform/api/lessons/{$locked->id}/playback")->assertStatus(403);
            $this->postJson("/platform/api/lessons/{$locked->id}/progress", ['position' => 5])->assertStatus(403);
            $this->postJson("/platform/api/lessons/{$locked->id}/complete")->assertStatus(403);
            $this->get("/platform/courses/{$this->mk->slug}/lessons/{$locked->id}")->assertOk()
                ->assertSee(__('course.locked_title'))->assertDontSee('data-playback', false)->assertDontSee('Mətn 3.1');
        }
        // Digər kurs: heç nə
        $other = Lesson::where('course_id', $this->dm->id)->first();
        $this->postJson("/platform/api/lessons/{$other->id}/playback")->assertStatus(403);
    }

    public function test_demo_user_does_not_see_homework_questions_live_or_mentoring(): void
    {
        $this->submit();
        $this->verifyAs($this->demoUser());
        $this->get("/platform/courses/{$this->mk->slug}/lessons/{$this->L[1][1]->id}")->assertOk()
            ->assertDontSee(__('course.tab_homework'))->assertDontSee(__('course.tab_questions'))
            ->assertSee(__('course.tab_materials'))->assertSee(__('course.tab_notes'));
    }

    public function test_demo_token_ttl_capped_by_demo_expiry_and_refresh_denied_after(): void
    {
        $this->submit();
        $this->verifyAs($this->demoUser());
        $this->travelTo($this->claims()->first()->expires_at->copy()->subMinutes(4));
        $r = $this->postJson("/platform/api/lessons/{$this->L[1][1]->id}/playback")->assertOk();
        $this->assertTrue(Carbon::parse($r->json('expires_at'))->lte($this->claims()->first()->expires_at));
        $this->travel(5)->minutes();
        $this->postJson("/platform/api/lessons/{$this->L[1][1]->id}/playback", ['kind' => 'refresh'])->assertStatus(403);
    }

    public function test_disposable_email_blocked_including_subdomains(): void
    {
        $this->submit(['email' => 'x@mailinator.com'])->assertSessionHasErrors('email');
        $this->submit(['email' => 'x@eu.mailinator.com'])->assertSessionHasErrors('email');
        $this->assertCount(0, $this->claims());
        $this->assertSame(0, $this->students());
    }

    public function test_honeypot_and_minimum_fill_time_silently_drop(): void
    {
        $this->submit(['bot-field' => 'x'])->assertRedirect(route('check-email', ['lang' => 'az']));
        $this->submit(['ts' => (string) (time() * 1000)])->assertRedirect(route('check-email', ['lang' => 'az']));
        $this->submit(['ts' => ''])->assertRedirect(route('check-email', ['lang' => 'az']));
        $this->assertSame(0, $this->students());
    }

    public function test_cross_site_submission_rejected(): void
    {
        $this->submit([], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(403);
        $this->submit([], ['Origin' => 'https://evil.example'])->assertStatus(403);
        $this->assertSame(0, $this->students());
    }

    public function test_registration_rate_limited_per_ip(): void
    {
        foreach (range(1, 10) as $i) {
            $this->submit(['email' => "rl{$i}@mail.az", 'tel' => '+99455'.str_pad((string) $i, 7, '0', STR_PAD_LEFT)]);
        }
        $this->submit(['email' => 'rl11@mail.az', 'tel' => '+994551231231'])->assertStatus(429);
    }

    public function test_existing_account_gets_email_not_new_account_and_can_start_demo_from_dashboard(): void
    {
        $u = $this->makeUser(['email' => 'existing@example.com', 'phone_e164' => '+994507777777']);
        $this->submit(['email' => 'existing@example.com', 'tel' => '+994507777777'])->assertRedirect(route('check-email', ['lang' => 'az']));
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
        $this->assertCount(0, $this->claims(), 'form vasitəsilə başqasının demosu başladıla bilməz');
        $this->assertSame(1, System::run(fn () => AppNotification::where('event_type', 'demo_existing_account')->count()));
        $this->signIn($u)->get('/platform/dashboard')->assertSee(__('demo.start'));
        $this->post("/platform/demo/start/{$this->dm->slug}")->assertRedirect(route('course.show', $this->dm));
        $this->assertSame('dashboard', $this->claims()->first()->source);
        $this->post("/platform/demo/start/{$this->mk->slug}")->assertStatus(403);
    }

    public function test_smtp_failure_retries_and_admin_can_resend(): void
    {
        $this->submit();
        Mail::extend('boom', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $m): void
            {
                throw new \RuntimeException('SMTP down');
            }

            public function __toString(): string
            {
                return 'boom';
            }
        });
        config(['mail.mailers.boom' => ['transport' => 'boom'], 'mail.default' => 'boom']);
        app(NotificationSender::class)->run();
        $n = System::run(fn () => AppNotification::where('event_type', 'demo_registered')->first());
        $this->assertSame('pending', $n->status);
        $this->assertSame(1, $n->attempts);
        $this->assertNotNull($n->last_error);

        config(['mail.default' => 'array']);
        Mail::forgetMailers();
        $admin = $this->makeUser([], ['admin']);
        $u = User::where('email', 'nigar.aliyeva@gmail.com')->first();
        $this->signIn($admin)->get('/platform/admin/demos')->assertOk()->assertSee('nigar.aliyeva@gmail.com');
        $this->post("/platform/admin/demos/{$u->id}/resend")->assertRedirect();
        $this->assertSame(1, \DB::table('audit_logs')->where('action', 'demo.link_resent_by_admin')->count());
        $this->travel(5)->minutes();
        app(NotificationSender::class)->run();
        $this->assertSame(2, System::run(fn () => AppNotification::where('event_type', 'demo_registered')->where('status', 'sent')->count()));
    }

    public function test_demo_reminders_scheduled_and_rls_hides_other_claims(): void
    {
        $this->submit();
        $claim = $this->claims()->first();
        $rows = System::run(fn () => AppNotification::where('entity_type', 'DemoClaim')->where('channel', 'email')->orderBy('scheduled_for')->get());
        $this->assertSame(['demo_ending_soon', 'demo_ended'], $rows->pluck('event_type')->all());
        $this->assertEquals($claim->expires_at->copy()->subHours(6), $rows[0]->scheduled_for);
        DbContext::actAs($this->makeUser()->id);
        $this->assertSame(0, \DB::table('demo_claims')->count());
    }

    public function test_demo_page_form_renders_in_three_languages(): void
    {
        $this->get('/platform/demo?lang=en')->assertOk()->assertSee('Free demo — 24-hour access')->assertSee('name="ts"', false);
        $this->get('/platform/demo?lang=ru')->assertOk()->assertSee('Бесплатное демо');
    }
}
