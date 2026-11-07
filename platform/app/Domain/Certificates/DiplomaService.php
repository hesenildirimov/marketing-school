<?php

namespace App\Domain\Certificates;

use App\Domain\Audit\Audit;
use App\Domain\Notifications\Notifier;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Diplom (bölmə 1b, 2.9):
 *   şərt: earned / possible * 100 >= 75; məxrəc = tələbənin KURS VERSİYASINDAKI məcburi tapşırıqların maksimum ballarının cəmi;
 *   göndərilməyən = 0; possible = 0 → diplom yoxdur; müqayisə tam ədədlərlə (earned*100 >= 75*possible) — ondalıq xətası yoxdur.
 *   Yaradılma idempotentdir (UNIQUE user_id+course_id), snapshot dəyişməzdir, diplom ödəniş statusundan asılı deyil.
 */
final class DiplomaService
{
    public const THRESHOLD = 75;

    /** @return array{earned:int, possible:int, percent_x10:int, eligible:bool} */
    public function score(Enrollment $e): array
    {
        $mandatory = DB::table('version_assignments')->where('course_version_id', $e->course_version_id)->where('is_mandatory', true)
            ->pluck('max_points', 'assignment_id');
        $possible = (int) $mandatory->sum();
        $earned = 0;
        if ($possible > 0) {
            $grades = DB::table('grades')->where('user_id', $e->user_id)->whereNull('superseded_at')
                ->whereIn('assignment_id', $mandatory->keys()->all())->pluck('points', 'assignment_id');
            foreach ($mandatory as $aid => $max) {
                // Verilmiş bal olduğu kimi sayılır; versiyada maksimum azalıbsa, yeni maksimumla məhdudlaşır.
                $earned += min((int) ($grades[$aid] ?? 0), (int) $max);
            }
        }
        $percentX10 = $possible > 0 ? intdiv($earned * 1000, $possible) : 0;

        return ['earned' => $earned, 'possible' => $possible, 'percent_x10' => $percentX10,
            'eligible' => $possible > 0 && $earned * 100 >= self::THRESHOLD * $possible];
    }

    public function evaluate(User $user, Course $course): ?Certificate
    {
        $e = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();
        if (! $e) {
            return null;
        }
        $existing = Certificate::where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($existing) {
            return $existing; // daimi: sonrakı dəyişikliklər verilmiş diplomu dəyişmir
        }
        $s = $this->score($e);
        if (! $s['eligible']) {
            return null;
        }
        $instructors = DB::table('course_instructors')->join('users', 'users.id', '=', 'course_instructors.user_id')
            ->where('course_id', $course->id)->pluck('users.name')->all();
        $snapshot = [
            'name' => $user->name, 'course' => $course->name, 'instructors' => $instructors,
            'earned' => $s['earned'], 'possible' => $s['possible'], 'percent' => number_format($s['percent_x10'] / 10, 1, '.', ''),
            'date' => now()->setTimezone(config('platform.display_timezone'))->toDateString(), 'course_version_id' => $e->course_version_id,
        ];
        $inserted = DB::table('certificates')->insertOrIgnore([
            'verify_id' => self::newVerifyId(), 'user_id' => $user->id, 'course_id' => $course->id,
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE), 'issued_at' => now(),
        ]);
        $cert = Certificate::where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($inserted) {
            DB::table('certificate_verifications')->insert(['verify_id' => $cert->verify_id, 'holder' => $snapshot['name'],
                'course' => json_encode($course->name, JSON_UNESCAPED_UNICODE), 'issued_at' => $cert->issued_at]);
            $e->update(['diploma_eligible_at' => $e->diploma_eligible_at ?? now()]);
            Notifier::send($user, 'diploma_issued', ['course' => $course->title(), 'url' => route('certificates.pdf', $cert)],
                ['entity' => ['Certificate', $cert->id]]);
            Audit::record('certificate.issued', $cert, after: ['percent' => $snapshot['percent']]);
        }

        return $cert;
    }

    public function revoke(Certificate $c, string $reason, User $admin): void
    {
        abort_unless($admin->isAdmin() && trim($reason) !== '', 422);
        $c->update(['revoked_at' => now(), 'revoke_reason' => $reason, 'revoked_by' => $admin->id]);
        DB::table('certificate_verifications')->where('verify_id', $c->verify_id)->update(['revoked_at' => now()]);
        Audit::record('certificate.revoked', $c, reason: $reason, actorId: $admin->id);
    }

    /** 128 bit random → 26 simvol base32 (təxmin olunmur, enumerasiyaya qarşı). */
    public static function newVerifyId(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(random_bytes(17)) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        for ($i = 0; $i < 26; $i++) {
            $out .= $alphabet[bindec(substr($bits, $i * 5, 5))];
        }

        return $out;
    }
}
