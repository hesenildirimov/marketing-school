<?php

namespace Tests\Feature;

use App\Domain\Analytics\AnalyticsService;
use App\Domain\Assessment\GradingService;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\NotificationController;
use App\Models\AppNotification;
use App\Models\Certificate;
use App\Models\DemoClaim;
use App\Models\UserSession;
use App\Support\DbContext;
use App\Support\System;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAssignments;
use Tests\Concerns\BuildsCourses;
use Tests\TestCase;

class AdminAndPrivacyTest extends TestCase
{
    use BuildsAssignments, BuildsCourses;

    public function test_analytics_aggregates_without_personal_data(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $admin = $this->makeUser([], ['admin']);
        foreach (range(1, 4) as $i) {
            $u = $this->makeUser(['email' => "demo{$i}@mail.az", 'phone_e164' => "+99450000000{$i}"]);
            System::run(fn () => DemoClaim::create(['user_id' => $u->id, 'course_id' => $c->id, 'email_normalized' => $u->email, 'phone_e164' => $u->phone_e164,
                'started_at' => now()->subDays(3)->startOfSecond(), 'expires_at' => now()->subDays(2)->startOfSecond(), 'converted_at' => $i === 1 ? now() : null]));
            if ($i === 2) {
                DbContext::actAs($u->id);
                DB::table('lesson_progress')->insert(['user_id' => $u->id, 'lesson_id' => $L[1][2]->id, 'course_id' => $c->id, 'max_watched_seconds' => 130, 'position_seconds' => 130, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $paid = $this->makeUser();
        $this->enroll($paid, $c);
        $late = $this->makeUser();
        $this->enroll($late, $c, now()->subDay());
        DbContext::actAs($admin->id);
        $m = app(AnalyticsService::class)->forCourse($c);
        $this->assertSame(4, $m['demo_registrations']);
        $this->assertSame(25.0, $m['demo_conversion_pct']);
        $this->assertSame(1, $m['statuses']['active']);
        $this->assertSame(1, $m['statuses']['past_due']);
        $this->assertSame(2, $m['demo_dropoff']['never_started']);
        $this->assertSame('Dərs 1.2', $m['demo_dropoff']['by_lesson'][0]['lesson']);
        $this->assertSame(2, $m['abandonment'][0]['peak_minute']);
        $json = json_encode($m);
        $this->assertStringNotContainsString('@mail.az', $json, 'şəxsi məlumat yoxdur');
        $this->signIn($admin)->get('/platform/admin/analytics')->assertOk()->assertSee(__('admin.m.demo_conversion_pct'))->assertDontSee('demo2@mail.az');
    }

    public function test_admin_sections_are_admin_only(): void
    {
        $t = $this->makeUser([], ['instructor']);
        $this->signIn($t);
        foreach (['/platform/admin/students', '/platform/admin/analytics', '/platform/admin/audit', '/platform/admin/notifications', '/platform/admin/billing', '/platform/admin/demos'] as $url) {
            $this->get($url)->assertStatus(403);
        }
        $this->get('/platform/admin/grading')->assertOk();
    }

    public function test_failed_notifications_visible_and_resend_is_audited(): void
    {
        $u = $this->makeUser(['email' => 'fail@mail.az']);
        Notifier::send($u, 'verify_email', ['url' => 'https://x'], ['channels' => ['email']]);
        System::run(fn () => AppNotification::query()->update(['status' => 'failed', 'attempts' => 6, 'last_error' => 'SMTP 421']));
        $admin = $this->makeUser([], ['admin']);
        $this->signIn($admin)->get('/platform/admin/notifications')->assertOk()->assertSee('fail@mail.az')->assertSee('SMTP 421');
        $n = System::run(fn () => AppNotification::first());
        $this->post("/platform/admin/notifications/{$n->id}/resend")->assertRedirect();
        $this->assertSame('pending', System::run(fn () => AppNotification::first()->status));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'notification.resent_by_admin')->count());
        $this->get('/platform/admin/audit')->assertOk()->assertSee(__('admin.chain_ok'))->assertSee('notification.resent_by_admin');
    }

    public function test_student_admin_view_and_session_revocation(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $s = $this->makeUser(['email' => 'stud@mail.az', 'name' => 'Rəşad Quliyev']);
        $this->enroll($s, $c);
        $admin = $this->makeUser([], ['admin']);
        $this->signIn($admin)->get('/platform/admin/students?q=rəşad')->assertOk()->assertSee('stud@mail.az');
        $this->get("/platform/admin/students/{$s->id}")->assertOk()->assertSee(__('course.status.active'));
        System::run(fn () => UserSession::create(['id' => str_repeat('a', 64), 'user_id' => $s->id, 'created_at' => now(), 'last_seen_at' => now()]));
        $this->post("/platform/admin/students/{$s->id}/sessions/revoke")->assertRedirect();
        $this->assertSame(0, System::run(fn () => UserSession::where('user_id', $s->id)->whereNull('revoked_at')->count()));
    }

    public function test_in_app_notifications_center(): void
    {
        $u = $this->makeUser();
        Notifier::send($u, 'payment_succeeded', ['course' => 'Marketinq', 'amount' => '99 AZN', 'until' => '01.12', 'url' => url('/platform/dashboard')], ['channels' => ['in_app']]);
        Notifier::send($this->makeUser(), 'payment_failed', ['course' => 'X'], ['channels' => ['in_app']]);
        $this->signIn($u)->get('/platform/dashboard')->assertSee('🔔')->assertSee('<span class="badge">1</span>', false);
        $this->get('/platform/notifications')->assertOk()->assertSee('Ödəniş qəbul edildi — Marketinq')->assertDontSee('Ödəniş alınmadı');
        $this->assertSame(0, NotificationController::unread($u->id));
    }

    public function test_data_export_contains_only_own_data(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $u = $this->makeUser(['email' => 'me@mail.az']);
        $other = $this->makeUser(['email' => 'other@mail.az']);
        $this->enroll($u, $c);
        $this->enroll($other, $c);
        $this->signIn($other)->postJson("/platform/api/lessons/{$L[1][1]->id}/notes", ['body' => 'başqasının qeydi']);
        $this->signIn($u)->postJson("/platform/api/lessons/{$L[1][1]->id}/notes", ['body' => 'mənim qeydim']);
        $r = $this->get('/platform/account/privacy/export')->assertOk();
        $this->assertStringContainsString('mənim qeydim', $r->getContent());
        $this->assertStringNotContainsString('başqasının qeydi', $r->getContent());
        $this->assertStringNotContainsString('other@mail.az', $r->getContent());
        $this->assertStringNotContainsString('password', $r->getContent());
    }

    public function test_account_deletion_anonymises_keeps_public_diploma_record_and_revokes_sessions(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        [$a] = $this->addAssignments($c, [[10, true]]);
        $u = $this->makeUser(['email' => 'leaving@mail.az', 'name' => 'Aynur Kərimova', 'phone_e164' => '+994507654321']);
        $this->enroll($u, $c);
        $t = $this->instructorFor($c);
        app(GradingService::class)->grade($this->submitAs($u, $a), 10, null, 'graded', $t);
        $verifyId = System::run(fn () => Certificate::first()->verify_id);
        System::run(fn () => DemoClaim::create(['user_id' => $u->id, 'course_id' => $c->id, 'email_normalized' => 'leaving@mail.az', 'phone_e164' => '+994507654321',
            'started_at' => now()->subDays(40)->startOfSecond(), 'expires_at' => now()->subDays(40)->startOfSecond()->addHours(24)]));
        $this->signIn($u)->delete('/platform/account', ['confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->delete('/platform/account', ['confirm' => 'SİL'])->assertRedirect(route('login'));
        $u->refresh();
        $this->assertNotNull($u->deleted_at);
        $this->assertSame('Silinmiş istifadəçi', $u->name);
        $this->assertNull($u->phone_e164);
        $this->assertStringNotContainsString('leaving', $u->email);
        $claim = System::run(fn () => DB::table('demo_claims')->where('user_id', $u->id)->first());
        $this->assertStringStartsWith('h:', $claim->email_normalized, 'demo identifikatorları açıq mətn deyil');
        $this->assertSame(0, System::run(fn () => UserSession::where('user_id', $u->id)->whereNull('revoked_at')->count()));
        $this->get("/platform/diplom/{$verifyId}")->assertOk()->assertSee('Aynur Kərimova'); // açıq yoxlama qeydi qalır
        $this->post('/platform/login', ['email' => 'leaving@mail.az', 'password' => 'Secret12345'])->assertSessionHasErrors('email');
    }

    public function test_staff_cannot_self_delete(): void
    {
        $this->signIn($this->makeUser([], ['admin']))->delete('/platform/account', ['confirm' => 'DELETE'])->assertStatus(422);
    }
}
