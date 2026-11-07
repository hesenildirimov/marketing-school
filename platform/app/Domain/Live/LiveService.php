<?php

namespace App\Domain\Live;

use App\Domain\Audit\Audit;
use App\Domain\Notifications\Notifier;
use App\Models\BillingRule;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\DbContext;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LiveService
{
    public const HOUR = 11;

    /** Ayın 2-ci və 4-cü şənbəsi (həftə nömrəsi ilə yox), 11:00 Asia/Baku → UTC. @return CarbonImmutable[] */
    public static function occurrences(int $year, int $month): array
    {
        $tz = 'Asia/Baku';
        $first = CarbonImmutable::create($year, $month, 1, self::HOUR, 0, 0, $tz);
        $firstSat = $first->dayOfWeek === 6 ? $first : $first->next(6)->setTime(self::HOUR, 0);

        return [$firstSat->addWeeks(1), $firstSat->addWeeks(3)];
    }

    /** Gələcək N ayın görüşlərini idempotent yaradır (təkrar işə salınma dublikat yaratmır). */
    public function generate(Course $course, int $months = 3): int
    {
        $created = 0;
        $now = CarbonImmutable::now('Asia/Baku');
        for ($i = 0; $i <= $months; $i++) {
            $m = $now->startOfMonth()->addMonths($i);
            foreach (self::occurrences($m->year, $m->month) as $local) {
                if ($local->lt($now) || $local->gt($now->addMonths($months))) {
                    continue;
                }
                $created += DB::table('live_sessions')->insertOrIgnore([
                    'course_id' => $course->id, 'occurrence_key' => $local->toDateString(), 'starts_at' => $local->utc(),
                    'duration_minutes' => 60, 'status' => 'scheduled', 'ics_uid' => (string) Str::uuid(), 'source' => 'auto',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $created;
    }

    /** Ödənişli (active/past_due) tələbələr — canlı görüş yalnız onlar üçündür. */
    public static function eligibleStudents(int $courseId): Collection
    {
        $grace = BillingRule::for($courseId)->grace_period_days;

        return User::whereIn('id', DB::table('enrollments')->where('course_id', $courseId)->whereNull('canceled_at')
            ->whereRaw('paid_until + make_interval(days => ?) >= now()', [$grace])->select('user_id'))->get();
    }

    public function update(LiveSession $s, CarbonImmutable $startsAt, int $duration, ?string $url, User $by): void
    {
        DbContext::as($by->id, fn () => DB::transaction(function () use ($s, $startsAt, $duration, $url, $by) {
            $before = ['starts_at' => $s->starts_at->toIso8601String(), 'duration' => $s->duration_minutes];
            $timeChanged = ! $s->starts_at->equalTo($startsAt) || $s->duration_minutes !== $duration;
            $s->fill(['starts_at' => $startsAt->utc(), 'duration_minutes' => $duration, 'source' => $timeChanged ? 'manual' : $s->source]);
            if ($url !== null) {
                $s->meeting_url_encrypted = $url ?: null;
            }
            if ($timeChanged) {
                $s->ics_sequence++;
            }
            $s->save();
            Audit::record('live.updated', $s, before: $before, after: ['starts_at' => $startsAt->toIso8601String(), 'duration' => $duration], actorId: $by->id);
            if ($timeChanged) {
                Notifier::cancelPending('LiveSession', $s->id);       // köhnə vaxtın xatırlatmaları dayandırılır
                $this->broadcast($s, 'live_changed', 'REQUEST');
            }
        }));
    }

    public function cancel(LiveSession $s, User $by, ?string $reason = null): void
    {
        DbContext::as($by->id, fn () => DB::transaction(function () use ($s, $by, $reason) {
            $s->update(['status' => 'canceled', 'ics_sequence' => $s->ics_sequence + 1]);
            Notifier::cancelPending('LiveSession', $s->id);
            Audit::record('live.canceled', $s, reason: $reason, actorId: $by->id);
            $this->broadcast($s, 'live_canceled', 'CANCEL');
        }));
    }

    public function ics(LiveSession $s, string $method = 'REQUEST', ?string $locale = null): string
    {
        $title = __('live.ics_summary', ['course' => tr_field($s->course->name, $locale)], $locale);

        return Ics::event($s->ics_uid, $s->ics_sequence, $s->starts_at, $s->endsAt(), $title,
            __('live.ics_description', [], $locale), route('live.index', $s->course), $method);
    }

    private function broadcast(LiveSession $s, string $event, string $method): void
    {
        foreach (self::eligibleStudents($s->course_id) as $u) {
            Notifier::send($u, $event, [
                'course' => tr_field($s->course->name, $u->locale), 'when' => Tz::fmt($s->starts_at),
                'url' => route('live.index', $s->course), 'ics' => $this->ics($s, $method, $u->locale), 'ics_method' => $method,
            ], ['entity' => ['LiveSessionChange', $s->id], 'dedupe' => $event.$s->ics_sequence]);
        }
    }

    /** Xatırlatmalar: görüşdən 24 saat və 1 saat əvvəl (cron hər 5 dəqiqədə; dedupe ikiqat göndərməni bloklayır). */
    public function scheduleReminders(): int
    {
        $n = 0;
        $sessions = LiveSession::where('status', 'scheduled')->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours(25))->get();
        foreach ($sessions as $s) {
            foreach (['live_reminder_24h' => 24, 'live_reminder_1h' => 1] as $event => $h) {
                $at = $s->starts_at->copy()->subHours($h);
                if ($at->gt(now()->addMinutes(10)) || $s->starts_at->lte(now()->addMinutes($h === 1 ? 5 : 60))) {
                    continue; // hələ tez və ya artıq gec
                }
                foreach (self::eligibleStudents($s->course_id) as $u) {
                    $n += Notifier::send($u, $event, ['course' => tr_field($s->course->name, $u->locale), 'when' => Tz::fmt($s->starts_at),
                        'url' => route('live.index', $s->course)], ['entity' => ['LiveSession', $s->id], 'at' => $at, 'dedupe' => $event.$s->ics_sequence, 'channels' => ['email']]);
                }
            }
        }

        return $n;
    }
}
