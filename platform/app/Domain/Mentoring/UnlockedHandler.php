<?php

namespace App\Domain\Mentoring;

use App\Domain\Notifications\Notifier;
use App\Models\BillingRule;
use App\Models\Enrollment;
use App\Models\Subscription;

/** Outbox: `enrollment.modules_completed` → "1:1 mentorluq açıldı" bildirişi. */
final class UnlockedHandler
{
    public function handle(array $p): void
    {
        $e = Enrollment::with('user', 'course')->find($p['enrollment_id']);
        // 1c-3: "kursu bitirəndə avtomatik ödənişi dayandır" qaydası (default söndürülüb)
        if ($e && BillingRule::for($e->course_id)->auto_stop_on_completion) {
            Subscription::where('enrollment_id', $e->id)->where('status', 'active')->update(['cancel_at_period_end' => true]);
        }
        if ($e && $e->mentoring_unlocked_at) {
            Notifier::send($e->user, 'mentoring_unlocked', ['course' => tr_field($e->course->name, $e->user->locale),
                'url' => route('mentoring.index', $e->course)], ['entity' => ['Enrollment', $e->id], 'dedupe' => 'unlocked']);
        }
    }
}
