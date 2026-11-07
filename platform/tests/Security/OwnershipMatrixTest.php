<?php

namespace Tests\Security;

use App\Domain\Live\LiveService;
use App\Models\LiveSession;
use App\Models\MentorBooking;
use App\Models\MentorSlot;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Support\DbContext;
use App\Support\System;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAssignments;
use Tests\Concerns\BuildsCourses;
use Tests\TestCase;

/**
 * Bölmə 6: "Hər həssas endpoint üçün ownership testi". Başqa tələbənin resursuna hər yoldan müraciət
 * 403/404 qaytarmalıdır (Policy qatı) və RLS səviyyəsində də görünməməlidir.
 */
class OwnershipMatrixTest extends TestCase
{
    use BuildsAssignments, BuildsCourses;

    public function test_student_cannot_touch_another_students_resources(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 1, lessonsPer: 1);
        [$a] = $this->addAssignments($c, [[10, true]]);
        $owner = $this->makeUser(['email' => 'owner@mail.az']);
        $intruder = $this->makeUser(['email' => 'intruder@mail.az']);
        $eo = $this->enroll($owner, $c);
        $this->enroll($intruder, $c);
        System::run(fn () => $eo->update(['mentoring_unlocked_at' => now()]));

        // Sahibin resursları
        $sub = $this->submitAs($owner, $a, 'sahibin cavabı');
        $t = $this->instructorFor($c);
        $slot = MentorSlot::create(['course_id' => $c->id, 'instructor_id' => $t->id, 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHour()]);
        $this->signIn($owner)->post("/platform/courses/{$c->slug}/mentoring", ['slot_id' => $slot->id])->assertRedirect();
        $booking = MentorBooking::first();
        config(['services.payment.provider' => 'fake']);
        $this->post("/platform/billing/checkout/{$c->slug}");
        $order = System::run(fn () => PaymentOrder::where('user_id', $owner->id)->first());
        System::run(fn () => Subscription::create(['enrollment_id' => $eo->id, 'user_id' => $owner->id, 'course_id' => $c->id, 'provider' => 'fake', 'status' => 'active']));
        $subscription = System::run(fn () => Subscription::where('user_id', $owner->id)->first());
        $this->postJson("/platform/api/lessons/{$L[1][1]->id}/notes", ['body' => 'gizli qeyd']);
        $note = DB::table('personal_notes')->where('user_id', $owner->id)->first();

        // Başqa tələbə — hamısı 403/404
        $this->signIn($intruder);
        $this->assertContains($this->get("/platform/admin/grading/{$sub->id}")->status(), [403, 404]);
        $this->assertContains($this->post("/platform/admin/grading/{$sub->id}", ['points' => 10, 'status' => 'graded'])->status(), [403, 404]);
        $this->post("/platform/mentoring/{$booking->id}/cancel")->assertNotFound();
        $this->post("/platform/mentoring/{$booking->id}/reschedule", ['slot_id' => $slot->id])->assertNotFound();
        $this->get("/platform/billing/return/{$order->id}")->assertNotFound();
        $this->getJson("/platform/api/billing/orders/{$order->id}")->assertNotFound();
        $this->post("/platform/billing/subscriptions/{$subscription->id}/cancel")->assertNotFound();
        $this->post("/platform/billing/subscriptions/{$subscription->id}/resume")->assertNotFound();
        $this->deleteJson("/platform/api/lessons/{$L[1][1]->id}/notes/{$note->id}")->assertNotFound();
        $this->get('/platform/account/privacy/export')->assertOk()->assertDontSee('gizli qeyd')->assertDontSee('sahibin cavabı');

        // RLS: tətbiq qatı yan keçilsə belə görünmür
        DbContext::actAs($intruder->id);
        foreach (['submissions', 'mentor_bookings', 'payment_orders', 'subscriptions', 'personal_notes', 'access_periods'] as $table) {
            $this->assertSame(0, DB::table($table)->where('user_id', $owner->id)->count(), "RLS: {$table}");
        }
        $this->assertSame('booked', System::run(fn () => MentorBooking::first()->status), 'başqasının rezervi toxunulmaz qalıb');
    }

    public function test_instructor_isolation_across_courses(): void
    {
        ['course' => $a] = $this->buildCourse('marketinq-kursu', modules: 1, lessonsPer: 1);
        ['course' => $b] = $this->buildCourse('digital-marketinq-kursu', modules: 1, lessonsPer: 1);
        [$asgB] = $this->addAssignments($b, [[10, true]]);
        $student = $this->makeUser();
        $this->enroll($student, $b);
        $subB = $this->submitAs($student, $asgB);
        $teacherA = $this->instructorFor($a);
        app(LiveService::class)->generate($b);
        $liveB = LiveSession::where('course_id', $b->id)->first();
        $this->signIn($teacherA);
        $this->get('/platform/admin/grading')->assertOk()->assertDontSee($student->email);
        $this->get("/platform/admin/grading/{$subB->id}")->assertStatus(404);
        $this->get("/platform/admin/courses/{$b->slug}")->assertStatus(403);
        $this->get("/platform/admin/live/{$liveB->id}")->assertStatus(403);
        $this->post("/platform/admin/live/{$liveB->id}/cancel")->assertStatus(403);
        $this->get("/platform/admin/courses/{$b->slug}/assignments/{$asgB->id}")->assertStatus(403);
        DbContext::actAs($teacherA->id);
        $this->assertSame(0, DB::table('submissions')->count(), 'RLS: başqa kursun göndərişləri görünmür');
        $this->assertSame(0, DB::table('enrollments')->where('course_id', $b->id)->count());
    }

    public function test_no_cors_headers_and_no_open_redirect(): void
    {
        $r = $this->withHeaders(['Origin' => 'https://evil.example'])->get('/platform/login');
        $this->assertNull($r->headers->get('Access-Control-Allow-Origin'));
        $r = $this->call('OPTIONS', '/platform/api/demo/register', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST']);
        $this->assertNull($r->headers->get('Access-Control-Allow-Origin'));
        // Giriş sonrası yönləndirmə yalnız sessiyadakı daxili URL-ə (url.intended) — xarici URL parametri qəbul edilmir
        $u = $this->makeUser(['email' => 'redir@mail.az']);
        $this->post('/platform/login?redirect=https://evil.example', ['email' => 'redir@mail.az', 'password' => 'Secret12345'])
            ->assertRedirect(route('dashboard'));
    }
}
