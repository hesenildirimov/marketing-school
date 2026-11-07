<?php

namespace App\Domain\Learning;

use App\Domain\Access\AccessPolicy;
use App\Domain\Content\Manifest;
use App\Domain\Notifications\Outbox;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Video progress və dərsin tamamlanması (bölmə 2.3, 1b).
 * - Yazma idempotent upsert-dir (PK user_id + lesson_id), paralel sorğular ikiqat sətir yaratmır.
 * - max_watched_seconds yalnız artır (GREATEST).
 * - İzləmə faizi dərsi TAMAMLAMIR; tamamlanma yalnız "Dərsi tamamladım" ilə, completed_at serverdə.
 */
final class ProgressService
{
    public function __construct(private AccessPolicy $access) {}

    public function heartbeat(User $user, Course $course, int $lessonId, int $position, ?int $duration): array
    {
        $duration = $duration && $duration > 0 ? min($duration, 86400) : $this->duration($lessonId);
        $position = max(0, $duration ? min($position, $duration) : $position);
        $percent = $duration ? round(min(100, $position / $duration * 100), 2) : 0;
        DB::statement(
            'INSERT INTO lesson_progress (user_id, lesson_id, course_id, position_seconds, max_watched_seconds, watch_percent, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, now(), now())
             ON CONFLICT (user_id, lesson_id) DO UPDATE SET
               position_seconds = EXCLUDED.position_seconds,
               max_watched_seconds = GREATEST(lesson_progress.max_watched_seconds, EXCLUDED.max_watched_seconds),
               watch_percent = GREATEST(lesson_progress.watch_percent, EXCLUDED.watch_percent),
               updated_at = now()',
            [$user->id, $lessonId, $course->id, $position, $position, $percent]
        );

        return (array) DB::table('lesson_progress')->where(['user_id' => $user->id, 'lesson_id' => $lessonId])->first();
    }

    /** Təkrar sorğu ilk completed_at-ı saxlayır (COALESCE), ikiqat progress yaranmır. */
    public function complete(User $user, Course $course, int $lessonId): array
    {
        return DB::transaction(function () use ($user, $course, $lessonId) {
            DB::statement(
                'INSERT INTO lesson_progress (user_id, lesson_id, course_id, completed_at, created_at, updated_at)
                 VALUES (?, ?, ?, now(), now(), now())
                 ON CONFLICT (user_id, lesson_id) DO UPDATE SET completed_at = COALESCE(lesson_progress.completed_at, now()), updated_at = now()',
                [$user->id, $lessonId, $course->id]
            );
            $this->recomputeMilestones($user, $course);

            return (array) DB::table('lesson_progress')->where(['user_id' => $user->id, 'lesson_id' => $lessonId])->first();
        });
    }

    /**
     * Modul/kurs mərhələləri: bütün dərslər tamamlananda modules_completed_at və mentoring_unlocked_at
     * bir dəfə yazılır. Sonradan yeni dərs əlavə olunsa artıq açılmış 1:1 hüququ itmir (bölmə 3.4).
     */
    public function recomputeMilestones(User $user, Course $course): void
    {
        $e = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->lockForUpdate()->first();
        if (! $e || ! $this->access->hasPaidAccess($user, $course)) {
            return;
        }
        $ids = Manifest::for($e->course_version_id)->lessonIds();
        if (! $ids) {
            return;
        }
        $done = DB::table('lesson_progress')->where('user_id', $user->id)->whereIn('lesson_id', $ids)->whereNotNull('completed_at')->count();
        if ($done === count($ids) && ! $e->modules_completed_at) {
            $e->modules_completed_at = now();
            $e->mentoring_unlocked_at ??= now();
            $e->save();
            Outbox::record('enrollment.modules_completed', ['enrollment_id' => $e->id]);
        }
    }

    public function courseProgress(User $user, Manifest $m): array
    {
        $ids = $m->lessonIds();
        $rows = DB::table('lesson_progress')->where('user_id', $user->id)->whereIn('lesson_id', $ids ?: [0])->get()->keyBy('lesson_id');
        $completed = $rows->whereNotNull('completed_at')->count();

        return ['rows' => $rows, 'completed' => $completed, 'total' => count($ids),
            'percent' => count($ids) ? (int) floor($completed / count($ids) * 100) : 0];
    }

    /** Modul yalnız bütün dərsləri tamamlananda tamamlanmış sayılır. */
    public function moduleCompleted(Manifest $m, int $moduleId, $rows): bool
    {
        $lessons = $m->lessonsOf($moduleId);

        return $lessons->isNotEmpty() && $lessons->every(fn ($l) => isset($rows[$l->id]) && $rows[$l->id]->completed_at);
    }

    private function duration(int $lessonId): ?int
    {
        return DB::table('lessons')->join('lesson_revisions as r', 'r.id', '=', 'lessons.current_revision_id')
            ->join('video_assets as v', 'v.id', '=', 'r.video_asset_id')->where('lessons.id', $lessonId)->value('v.duration_seconds');
    }
}
