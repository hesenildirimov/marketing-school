<?php

namespace App\Domain\Access;

use App\Domain\Content\Manifest;
use App\Models\BillingRule;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TƏK icazə qatı (bölmə 4.2). Bütün kurs üzrə giriş qərarları buradan keçir və serverdə hesablanır.
 * Qlobal rol yox, kurs üzrə enrollment və demo_claim hüquqları təyin edir (bölmə 2.1).
 *
 * Enrollment statusu (bölmə 1b):
 *   canceled  — admin və ya refund (canceled_at)
 *   active    — now <= paid_until
 *   past_due  — paid_until < now <= paid_until + grace_period_days
 *   expired   — now > grace_until
 * Yalnız active və past_due giriş verir.
 */
final class AccessPolicy
{
    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const EXPIRED = 'expired';

    public const CANCELED = 'canceled';

    public const NONE = 'none';

    public function enrollment(User $user, Course|int $course): ?Enrollment
    {
        $cid = is_int($course) ? $course : $course->id;

        return Enrollment::where('user_id', $user->id)->where('course_id', $cid)->first();
    }

    public function graceUntil(Enrollment $e): ?CarbonInterface
    {
        return $e->paid_until?->copy()->addDays(BillingRule::for($e->course_id)->grace_period_days);
    }

    public function status(?Enrollment $e, ?CarbonInterface $now = null): string
    {
        $now ??= now();
        if (! $e) {
            return self::NONE;
        }
        if ($e->canceled_at) {
            return self::CANCELED;
        }
        if (! $e->paid_until) {
            return self::EXPIRED;
        }
        if ($now->lte($e->paid_until)) {
            return self::ACTIVE;
        }

        return $now->lte($this->graceUntil($e)) ? self::PAST_DUE : self::EXPIRED;
    }

    public function hasPaidAccess(User $user, Course|int $course): bool
    {
        return in_array($this->status($this->enrollment($user, $course)), [self::ACTIVE, self::PAST_DUE], true);
    }

    public function isStaffFor(User $user, Course|int $course): bool
    {
        $cid = is_int($course) ? $course : $course->id;

        return $user->isAdmin() || in_array($cid, $user->taughtCourseIds(), true);
    }

    /** Aktiv demo hüququ (yalnız seçilmiş kurs, 24 saat, e-mail təsdiqli və ya təsdiqsiz). */
    public function activeDemo(User $user, Course|int $course): ?object
    {
        if (! Schema::hasTable('demo_claims')) {
            return null;
        }
        $cid = is_int($course) ? $course : $course->id;
        $claim = DB::table('demo_claims')->where('user_id', $user->id)->where('course_id', $cid)->first();
        if (! $claim || now()->gte(CarbonImmutable::parse($claim->expires_at))) {
            return null;
        }

        return $claim;
    }

    public function demoClaim(User $user): ?object
    {
        return Schema::hasTable('demo_claims') ? DB::table('demo_claims')->where('user_id', $user->id)->first() : null;
    }

    /** Tələbənin gördüyü manifest: ödənişli → enrollment versiyası; demo/staff → kursun cari versiyası. */
    public function manifestFor(User $user, Course $course): ?Manifest
    {
        $e = $this->enrollment($user, $course);
        if ($e && in_array($this->status($e), [self::ACTIVE, self::PAST_DUE], true)) {
            return Manifest::for($e->course_version_id);
        }

        return $course->current_version_id ? Manifest::for($course->current_version_id) : null;
    }

    /** Dərsə giriş (video tokeni, material, qeydlər, tamamlama) — kilidli dərs serverdə heç verilmir. */
    public function canViewLesson(User $user, Course $course, int $lessonId): bool
    {
        if ($this->isStaffFor($user, $course)) {
            return true;
        }
        if ($this->hasPaidAccess($user, $course)) {
            return Manifest::for($this->enrollment($user, $course)->course_version_id)->hasLesson($lessonId);
        }
        if ($this->activeDemo($user, $course) && $course->current_version_id) {
            $m = Manifest::for($course->current_version_id);
            $module = $m->moduleOf($lessonId);

            return $module !== null && (bool) $module->demo_open;
        }

        return false;
    }

    /** Kursa ümumiyyətlə girə bilirmi (siyahını görmək üçün): ödənişli, staff və ya aktiv demo. */
    public function canEnterCourse(User $user, Course $course): bool
    {
        return $this->isStaffFor($user, $course) || $this->hasPaidAccess($user, $course) || $this->activeDemo($user, $course) !== null;
    }

    /** Ev tapşırığı, canlı görüş, suallar, yazılar: yalnız ödənişli (və staff). Demo HEÇ görmür. */
    public function canSeePaidFeatures(User $user, Course $course): bool
    {
        return $this->isStaffFor($user, $course) || $this->hasPaidAccess($user, $course);
    }

    /** 1:1 mentorluq: ödənişli + bütün dərslər tamamlanıb (açılma qeydə alınıb, sonradan itmir). */
    public function canUseMentoring(User $user, Course $course): bool
    {
        $e = $this->enrollment($user, $course);

        return $e && $this->hasPaidAccess($user, $course) && $e->mentoring_unlocked_at !== null;
    }

    /** Video tokeninin maksimum bitmə vaxtı: demo → demo_expires_at, ödənişli → grace_until. */
    public function accessDeadline(User $user, Course $course): ?CarbonInterface
    {
        if ($this->isStaffFor($user, $course)) {
            return now()->addDay();
        }
        $e = $this->enrollment($user, $course);
        if ($e && in_array($this->status($e), [self::ACTIVE, self::PAST_DUE], true)) {
            return $this->graceUntil($e);
        }
        $demo = $this->activeDemo($user, $course);

        return $demo ? CarbonImmutable::parse($demo->expires_at) : null;
    }
}
