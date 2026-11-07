<?php

namespace App\Domain\Analytics;

use App\Domain\Access\AccessPolicy;
use App\Domain\Content\Manifest;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * Admin analitikası (bölmə 2.11b): yalnız YIĞILMIŞ rəqəmlər, şəxsi məlumat göstərilmir.
 * Admin RLS konteksti ilə işləyir (app.is_admin()).
 */
final class AnalyticsService
{
    public function __construct(private AccessPolicy $access) {}

    public function forCourse(Course $c, int $days = 30): array
    {
        $since = now()->subDays($days);
        $claims = DB::table('demo_claims')->where('course_id', $c->id);
        $demoTotal = (clone $claims)->count();
        $converted = (clone $claims)->whereNotNull('converted_at')->count();
        $statuses = ['active' => 0, 'past_due' => 0, 'expired' => 0, 'canceled' => 0];
        $enrollments = Enrollment::where('course_id', $c->id)->get();
        foreach ($enrollments as $e) {
            $s = $this->access->status($e);
            if (isset($statuses[$s])) {
                $statuses[$s]++;
            }
        }
        $manifest = $c->current_version_id ? Manifest::for($c->current_version_id) : null;

        return [
            'demo_registrations' => $demoTotal,
            'demo_registrations_period' => (clone $claims)->where('started_at', '>=', $since)->count(),
            'demo_conversion_pct' => $demoTotal ? round($converted / $demoTotal * 100, 1) : 0.0,
            'demo_dropoff' => $this->demoDropoff($c, $manifest),
            'statuses' => $statuses,
            'recurring_pct' => $this->recurring($c),
            'avg_progress_pct' => $this->avgProgress($enrollments),
            'abandonment' => $this->abandonment($c, $manifest),
            'submission_rate_pct' => $this->submissionRate($c, $enrollments),
            'avg_grading_hours' => $this->gradingHours($c),
            'diplomas' => DB::table('certificates')->where('course_id', $c->id)->whereNull('revoked_at')->count(),
            'revenue' => $this->revenue($c),
            'failed_payments_period' => DB::table('payment_orders')->where('course_id', $c->id)->where('status', 'failed')->where('created_at', '>=', $since)->count(),
            'player' => $this->player($c),
        ];
    }

    /** Konvert olunmamış, bitmiş demolar: istifadəçinin çatdığı son dərs (kurs sırası ilə) üzrə say. */
    private function demoDropoff(Course $c, ?Manifest $m): array
    {
        if (! $m) {
            return [];
        }
        $order = array_flip($m->lessonIds());
        $rows = DB::table('demo_claims as d')->join('lesson_progress as p', 'p.user_id', '=', 'd.user_id')
            ->where('d.course_id', $c->id)->whereNull('d.converted_at')->where('d.expires_at', '<', now())->where('p.course_id', $c->id)
            ->select('d.user_id', 'p.lesson_id')->get()->groupBy('user_id');
        $last = [];
        foreach ($rows as $lessons) {
            $max = $lessons->sortByDesc(fn ($r) => $order[$r->lesson_id] ?? -1)->first();
            $last[$max->lesson_id] = ($last[$max->lesson_id] ?? 0) + 1;
        }
        $neverStarted = DB::table('demo_claims')->where('course_id', $c->id)->whereNull('converted_at')->where('expires_at', '<', now())
            ->whereNotIn('user_id', $rows->keys()->all() ?: [0])->count();
        $out = [];
        foreach ($m->lessons as $l) {
            if (isset($last[$l->id])) {
                $out[] = ['lesson' => tr_field($l->title), 'users' => $last[$l->id]];
            }
        }

        return ['never_started' => $neverStarted, 'by_lesson' => $out];
    }

    /** Təkrar aylıq ödəniş faizi: ilk ödənişdən sonra ən azı bir dəfə yenidən ödəyən enrollment-lərin payı. */
    private function recurring(Course $c): float
    {
        $counts = DB::table('access_periods')->where('course_id', $c->id)->where('source', 'payment')->whereNull('revoked_at')
            ->select('enrollment_id', DB::raw('count(*) as n'))->groupBy('enrollment_id')->pluck('n');
        $eligible = $counts->count();

        return $eligible ? round($counts->filter(fn ($n) => $n >= 2)->count() / $eligible * 100, 1) : 0.0;
    }

    private function avgProgress($enrollments): float
    {
        $vals = [];
        foreach ($enrollments as $e) {
            $ids = Manifest::for($e->course_version_id)->lessonIds();
            if ($ids) {
                $done = DB::table('lesson_progress')->where('user_id', $e->user_id)->whereIn('lesson_id', $ids)->whereNotNull('completed_at')->count();
                $vals[] = $done / count($ids) * 100;
            }
        }

        return $vals ? round(array_sum($vals) / count($vals), 1) : 0.0;
    }

    /** Video abandonment: tamamlanmamış izləmələrdə max_watched dəqiqəsinin paylanması (dərs üzrə). */
    private function abandonment(Course $c, ?Manifest $m): array
    {
        $rows = DB::table('lesson_progress')->where('course_id', $c->id)->whereNull('completed_at')->where('max_watched_seconds', '>', 0)
            ->select('lesson_id', DB::raw('floor(max_watched_seconds / 60)::int as minute'), DB::raw('count(*) as n'))
            ->groupBy('lesson_id', 'minute')->get()->groupBy('lesson_id');
        $titles = $m ? $m->lessons->pluck('title', 'id') : collect();
        $out = [];
        foreach ($rows as $lid => $buckets) {
            $top = $buckets->sortByDesc('n')->first();
            $out[] = ['lesson' => tr_field($titles[$lid] ?? ['az' => "#{$lid}"]), 'peak_minute' => (int) $top->minute, 'viewers' => (int) $buckets->sum('n')];
        }

        return collect($out)->sortByDesc('viewers')->take(10)->values()->all();
    }

    private function submissionRate(Course $c, $enrollments): float
    {
        $expected = 0;
        foreach ($enrollments as $e) {
            $expected += DB::table('version_assignments')->where('course_version_id', $e->course_version_id)->count();
        }
        $actual = DB::table('submissions')->where('course_id', $c->id)->count();

        return $expected ? round(min(100, $actual / $expected * 100), 1) : 0.0;
    }

    private function gradingHours(Course $c): ?float
    {
        $v = DB::table('submissions as s')->join(DB::raw('(SELECT submission_id, min(created_at) AS first_graded FROM grades GROUP BY submission_id) g'), 'g.submission_id', '=', 's.id')
            ->where('s.course_id', $c->id)->selectRaw('avg(extract(epoch from (g.first_graded - s.submitted_at)) / 3600) as h')->value('h');

        return $v !== null ? round((float) $v, 1) : null;
    }

    private function revenue(Course $c): array
    {
        return DB::table('payment_transactions')->where('course_id', $c->id)->where('occurred_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("to_char(occurred_at AT TIME ZONE 'Asia/Baku', 'YYYY-MM') as month, sum(amount_qepik) as net_qepik, count(*) filter (where kind = 'charge') as charges")
            ->groupBy('month')->orderBy('month')->get()->map(fn ($r) => ['month' => $r->month, 'net_azn' => round($r->net_qepik / 100, 2), 'charges' => (int) $r->charges])->all();
    }

    private function player(Course $c): array
    {
        return DB::table('player_metrics as pm')->join('lessons as l', 'l.id', '=', 'pm.lesson_id')->where('l.course_id', $c->id)
            ->where('pm.created_at', '>=', now()->subDays(30))
            ->selectRaw("coalesce(pm.device, 'n/a') as device, count(*) as sessions, round(avg(pm.ttff_ms)) as ttff_ms,
                percentile_cont(0.75) within group (order by pm.ttff_ms) as ttff_p75, round(avg(pm.rebuffer_count), 2) as rebuffers,
                round(avg(pm.avg_bitrate_kbps)) as kbps, round(avg(pm.quality_switches), 2) as switches")
            ->groupBy('device')->get()->map(fn ($r) => (array) $r)->all();
    }
}
