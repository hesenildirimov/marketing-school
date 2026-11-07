<?php

namespace App\Domain\Mentoring;

use App\Domain\Access\AccessPolicy;
use App\Domain\Audit\Audit;
use App\Domain\Live\Ics;
use App\Domain\Notifications\Notifier;
use App\Models\BillingRule;
use App\Models\Course;
use App\Models\MentorBooking;
use App\Models\MentorSlot;
use App\Models\User;
use App\Support\Tz;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 1:1 mentorluq rezervi (bölmə 2.7). Atomiklik verilənlər bazasındadır:
 *  - slot FOR UPDATE ilə kilidlənir; partial UNIQUE(slot_id) WHERE booked → eyni slotu iki nəfər tuta bilməz;
 *  - partial UNIQUE(enrollment_id) WHERE booked/completed/no_show → bir hüquq çoxaldılmır;
 *  - ləğv/köçürmə qaydası (default 24 saat) serverdə tətbiq olunur.
 */
final class MentoringService
{
    public function __construct(private AccessPolicy $access) {}

    public function book(User $user, Course $course, int $slotId): MentorBooking
    {
        abort_unless($this->access->canUseMentoring($user, $course), 403);
        $e = $this->access->enrollment($user, $course);
        try {
            return DB::transaction(function () use ($user, $course, $slotId, $e) {
                $slot = MentorSlot::where('id', $slotId)->where('course_id', $course->id)->whereNull('deleted_at')->lockForUpdate()->first();
                if (! $slot || $slot->is_booked || $slot->starts_at->lte(now()->addHour())) {
                    throw ValidationException::withMessages(['slot' => __('mentoring.slot_unavailable')]);
                }
                $slot->update(['is_booked' => true]);
                $b = MentorBooking::create(['slot_id' => $slot->id, 'enrollment_id' => $e->id, 'user_id' => $user->id,
                    'course_id' => $course->id, 'status' => 'booked', 'booked_at' => now()]);
                $this->notifyBooked($b, $slot, $user, $course);
                Audit::record('mentoring.booked', $b, after: ['slot' => $slot->id], actorId: $user->id);

                return $b;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slot' => __('mentoring.slot_taken_or_used')]);
        }
    }

    public function cancel(MentorBooking $b, User $by, bool $admin = false): void
    {
        DB::transaction(function () use ($b, $by, $admin) {
            $b = MentorBooking::lockForUpdate()->find($b->id);
            abort_unless($b->status === 'booked', 422);
            abort_unless($admin || $b->user_id === $by->id, 403);
            $hours = BillingRule::for($b->course_id)->mentoring_cancel_hours;
            if (! $admin && $b->slot->starts_at->lt(now()->addHours($hours))) {
                throw ValidationException::withMessages(['booking' => __('mentoring.too_late', ['h' => $hours])]);
            }
            $b->update(['status' => 'canceled', 'canceled_at' => now(), 'cancel_reason' => $admin ? 'admin' : 'student']);
            MentorSlot::where('id', $b->slot_id)->update(['is_booked' => false]);   // slot yenidən boşdur
            Notifier::cancelPending('MentorBooking', $b->id);
            $course = Course::find($b->course_id);
            $u = User::find($b->user_id);
            Notifier::send($u, 'mentoring_canceled', ['course' => tr_field($course->name, $u->locale), 'when' => Tz::fmt($b->slot->starts_at),
                'url' => route('mentoring.index', $course), 'ics' => $this->ics($b, $course, 'CANCEL', 1), 'ics_method' => 'CANCEL'],
                ['entity' => ['MentorBookingChange', $b->id], 'dedupe' => 'cancel']);
            Audit::record('mentoring.canceled', $b, actorId: $by->id);
        });
    }

    /** Köçürmə = köhnəni ləğv + yenini rezerv, bir tranzaksiyada (hüquq itmir, çoxalmır). */
    public function reschedule(MentorBooking $b, User $user, int $newSlotId): MentorBooking
    {
        return DB::transaction(function () use ($b, $user, $newSlotId) {
            $this->cancel($b, $user);

            return $this->book($user, Course::find($b->course_id), $newSlotId);
        });
    }

    public function ics(MentorBooking $b, Course $course, string $method = 'REQUEST', int $seq = 0): string
    {
        return Ics::event('mentor-'.$b->id, $seq, $b->slot->starts_at, $b->slot->ends_at,
            __('mentoring.ics_summary', ['course' => $course->title()]), __('mentoring.ics_description'), route('mentoring.index', $course), $method);
    }

    private function notifyBooked(MentorBooking $b, MentorSlot $slot, User $u, Course $course): void
    {
        $data = ['course' => tr_field($course->name, $u->locale), 'when' => Tz::fmt($slot->starts_at), 'url' => route('mentoring.index', $course)];
        Notifier::send($u, 'mentoring_booked', $data + ['ics' => $this->ics($b, $course), 'ics_method' => 'REQUEST'], ['entity' => ['MentorBookingChange', $b->id], 'dedupe' => 'booked']);
        foreach (['mentoring_reminder_24h' => 24, 'mentoring_reminder_1h' => 1] as $event => $h) {
            if ($slot->starts_at->copy()->subHours($h)->gt(now())) {
                Notifier::send($u, $event, $data, ['entity' => ['MentorBooking', $b->id], 'at' => $slot->starts_at->copy()->subHours($h), 'channels' => ['email']]);
            }
        }
        if ($instructor = $slot->instructor) {
            Notifier::send($instructor, 'mentoring_booked_instructor', $data + ['student' => $u->name], ['entity' => ['MentorBookingChange', $b->id], 'dedupe' => 'instr']);
        }
    }
}
