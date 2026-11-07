<?php

namespace App\Domain\Assessment;

use App\Domain\Audit\Audit;
use App\Domain\Certificates\DiplomaService;
use App\Domain\Notifications\Notifier;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Submission;
use App\Models\User;
use App\Support\DbContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Qiymətləndirmə: hər qiymət yeni sətirdir (köhnə superseded_at ilə saxlanılır), max_points snapshot-ı yazılır,
 * dəyişiklik audit log-a düşür, tələbəyə bildiriş gedir, diplom hüququ yenidən hesablanır.
 */
final class GradingService
{
    public function __construct(private DiplomaService $diplomas) {}

    public function grade(Submission $sub, int $points, ?string $feedback, string $status, User $grader): Grade
    {
        if (! in_array($status, ['graded', 'needs_revision'], true)) {
            throw ValidationException::withMessages(['status' => 'invalid']);
        }

        // Əməliyyat qiymətləndirən adından (RLS: yalnız öz kursunun göndərişləri)
        return DbContext::as($grader->id, fn () => DB::transaction(function () use ($sub, $points, $feedback, $status, $grader) {
            $sub = Submission::lockForUpdate()->find($sub->id);
            $e = Enrollment::where('user_id', $sub->user_id)->where('course_id', $sub->course_id)->firstOrFail();
            $max = DB::table('version_assignments')->where(['course_version_id' => $e->course_version_id, 'assignment_id' => $sub->assignment_id])->value('max_points');
            abort_unless($max, 422, 'assignment not in student version');
            if ($points < 0 || $points > $max) {
                throw ValidationException::withMessages(['points' => __('assess.points_range', ['max' => $max])]);
            }
            $prev = Grade::where('submission_id', $sub->id)->whereNull('superseded_at')->first();
            $prev?->update(['superseded_at' => now()]);
            $g = Grade::create([
                'submission_id' => $sub->id, 'user_id' => $sub->user_id, 'course_id' => $sub->course_id, 'assignment_id' => $sub->assignment_id,
                'points' => $points, 'max_points_snapshot' => (int) $max, 'course_version_id' => $e->course_version_id,
                'graded_by' => $grader->id, 'feedback' => $feedback, 'created_at' => now(),
            ]);
            $sub->update(['status' => $status]);
            Audit::record($prev ? 'grade.changed' : 'grade.given', $sub,
                before: $prev ? ['points' => $prev->points, 'max' => $prev->max_points_snapshot] : null,
                after: ['points' => $points, 'max' => (int) $max, 'status' => $status], actorId: $grader->id);
            $student = User::find($sub->user_id);
            Notifier::send($student, 'assignment_graded', ['assignment' => $sub->assignment->label(), 'points' => $points, 'max' => (int) $max,
                'url' => route('course.show', $sub->assignment->course)], ['entity' => ['Grade', $g->id], 'dedupe' => (string) $g->id]);
            $this->diplomas->evaluate($student, $sub->assignment->course);

            return $g;
        }));
    }
}
