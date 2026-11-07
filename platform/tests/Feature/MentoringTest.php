<?php

namespace Tests\Feature;

use App\Domain\Access\AccessPolicy;
use App\Domain\Content\VersionService;
use App\Domain\Mentoring\MentoringService;
use App\Domain\Notifications\Outbox;
use App\Models\AppNotification;
use App\Models\MentorBooking;
use App\Models\MentorSlot;
use App\Models\Module;
use App\Support\DbContext;
use App\Support\System;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsCourses;
use Tests\Support\Parallel;
use Tests\TestCase;

/** Bölmə 7 — Mentorluq testləri (race condition daxil). */
class MentoringTest extends TestCase
{
    use BuildsCourses;

    private function unlockedStudent($c)
    {
        $u = $this->makeUser();
        $e = $this->enroll($u, $c);
        System::run(fn () => $e->update(['mentoring_unlocked_at' => now()]));

        return $u;
    }

    private function slots($c, int $n = 3, int $startHours = 72): array
    {
        $t = $this->makeUser([], ['instructor']);
        DB::table('course_instructors')->insert(['course_id' => $c->id, 'user_id' => $t->id]);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = MentorSlot::create(['course_id' => $c->id, 'instructor_id' => $t->id, 'starts_at' => now()->addHours($startHours + $i),
                'ends_at' => now()->addHours($startHours + $i)->addMinutes(45), 'meeting_url_encrypted' => 'https://meet.google.com/abc-'.$i]);
        }

        return $out;
    }

    public function test_hidden_and_403_for_unpaid_and_demo_locked_until_all_lessons_done(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 1, lessonsPer: 1);
        [$slot] = $this->slots($c);
        $this->signIn($this->makeUser())->get("/platform/courses/{$c->slug}/mentoring")->assertStatus(403);
        $this->post("/platform/courses/{$c->slug}/mentoring", ['slot_id' => $slot->id])->assertStatus(403);
        $this->get('/platform/dashboard')->assertDontSee(__('mentoring.title'));

        $paid = $this->makeUser();
        $this->enroll($paid, $c);
        $this->signIn($paid)->get("/platform/courses/{$c->slug}/mentoring")->assertOk()->assertSee(__('mentoring.locked'))->assertDontSee('slot_id');
        $this->post("/platform/courses/{$c->slug}/mentoring", ['slot_id' => $slot->id])->assertStatus(403);
        $this->postJson("/platform/api/lessons/{$L[1][1]->id}/complete");
        $this->get("/platform/courses/{$c->slug}/mentoring")->assertOk()->assertSee('slot_id', false);
        $this->post("/platform/courses/{$c->slug}/mentoring", ['slot_id' => $slot->id])->assertRedirect();
        $this->assertSame(1, MentorBooking::where('user_id', $paid->id)->where('status', 'booked')->count());
    }

    public function test_two_students_racing_for_same_slot_only_one_wins(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        [$slot] = $this->slots($c, 1);
        $students = [$this->unlockedStudent($c), $this->unlockedStudent($c), $this->unlockedStudent($c), $this->unlockedStudent($c)];
        $res = Parallel::run(4, function (int $i) use ($students, $c, $slot) {
            DbContext::actAs($students[$i]->id);
            try {
                app(MentoringService::class)->book($students[$i], $c, $slot->id);

                return 'ok';
            } catch (ValidationException) {
                return 'rejected';
            }
        });
        $this->assertSame(1, count(array_keys($res, 'ok')), json_encode($res));
        $this->assertSame(3, count(array_keys($res, 'rejected')), json_encode($res));
        $this->assertSame(1, System::run(fn () => MentorBooking::where('slot_id', $slot->id)->where('status', 'booked')->count()));
    }

    public function test_one_student_cannot_multiply_the_right_even_in_parallel(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $slots = $this->slots($c, 4);
        $u = $this->unlockedStudent($c);
        $res = Parallel::run(4, function (int $i) use ($u, $c, $slots) {
            DbContext::actAs($u->id);
            try {
                app(MentoringService::class)->book($u, $c, $slots[$i]->id);

                return 'ok';
            } catch (ValidationException) {
                return 'rejected';
            }
        });
        $this->assertSame(1, count(array_keys($res, 'ok')), json_encode($res));
        $this->assertSame(1, System::run(fn () => MentorBooking::where('user_id', $u->id)->whereIn('status', ['booked'])->count()));
    }

    public function test_cancel_rule_enforced_on_server_and_slot_becomes_free_again(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        [$soon] = $this->slots($c, 1, startHours: 30);
        $u = $this->unlockedStudent($c);
        $this->signIn($u)->post("/platform/courses/{$c->slug}/mentoring", ['slot_id' => $soon->id]);
        $b = MentorBooking::firstOrFail();
        $this->assertTrue($soon->fresh()->is_booked);
        $this->travel(7)->hours();  // görüşə 23 saat qalıb
        $this->post("/platform/mentoring/{$b->id}/cancel")->assertSessionHasErrors('booking');
        $this->assertSame('booked', $b->fresh()->status);
        $this->travelBack();
        $this->post("/platform/mentoring/{$b->id}/cancel")->assertSessionHas('status');
        $this->assertSame('canceled', $b->fresh()->status);
        $this->assertFalse($soon->fresh()->is_booked, 'ləğv olunan slot yenidən boşdur');
        $other = $this->unlockedStudent($c);
        $this->signIn($other)->post("/platform/courses/{$c->slug}/mentoring", ['slot_id' => $soon->id])->assertRedirect();
        $this->assertSame(1, System::run(fn () => MentorBooking::where('slot_id', $soon->id)->where('status', 'booked')->count()));
    }

    public function test_reschedule_keeps_single_right_and_sends_ics(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        [$a, $b2] = $this->slots($c, 2);
        $u = $this->unlockedStudent($c);
        $this->signIn($u)->post("/platform/courses/{$c->slug}/mentoring", ['slot_id' => $a->id]);
        $b = MentorBooking::firstOrFail();
        $this->post("/platform/mentoring/{$b->id}/reschedule", ['slot_id' => $b2->id])->assertSessionHas('status');
        $this->assertSame(1, MentorBooking::where('user_id', $u->id)->where('status', 'booked')->count());
        $this->assertSame($b2->id, MentorBooking::where('user_id', $u->id)->where('status', 'booked')->value('slot_id'));
        $mail = System::run(fn () => AppNotification::where('user_id', $u->id)->where('event_type', 'mentoring_booked')->where('channel', 'email')->get());
        $this->assertCount(2, $mail);
        $this->assertStringContainsString('BEGIN:VEVENT', $mail[0]->payload['ics']);
        // köhnə rezervin xatırlatmaları ləğv olunub, yenisininki gözləyir
        $rem = System::run(fn () => AppNotification::where('user_id', $u->id)->where('event_type', 'mentoring_reminder_24h')->pluck('status')->sort()->values()->all());
        $this->assertSame(['canceled', 'pending'], $rem);
    }

    public function test_instructor_slots_cannot_overlap(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        [$s] = $this->slots($c, 1);
        $this->expectException(QueryException::class);
        MentorSlot::create(['course_id' => $c->id, 'instructor_id' => $s->instructor_id, 'starts_at' => $s->starts_at->copy()->addMinutes(10), 'ends_at' => $s->ends_at->copy()->addMinutes(10)]);
    }

    public function test_unlocked_right_survives_new_lesson(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $this->signIn($u)->postJson("/platform/api/lessons/{$L[1][1]->id}/complete");
        $vs = app(VersionService::class);
        $d = $vs->draft($c->fresh());
        $vs->addLesson($d, Module::first(), ['az' => 'Yeni']);
        $vs->publish($d, 'all', 'yeni dərs', $this->makeUser([], ['admin']));
        $this->assertTrue(app(AccessPolicy::class)->canUseMentoring($u, $c->fresh()));
        Outbox::process();
        $this->assertSame(1, System::run(fn () => AppNotification::where('user_id', $u->id)->where('event_type', 'mentoring_unlocked')->where('channel', 'email')->count()));
    }
}
