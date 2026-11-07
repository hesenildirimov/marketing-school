<?php

namespace App\Domain\Assessment;

use App\Domain\Audit\Audit;
use App\Domain\Content\Manifest;
use App\Models\Assignment;
use App\Models\AssignmentRevision;
use App\Models\CourseVersion;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;
use App\Support\DbContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignmentService
{
    /** Yeni tapşırıq qaralama versiyaya əlavə olunur (struktur dəyişikliyi). */
    public function create(CourseVersion $draft, Module $module, array $title, array $body, int $maxPoints, bool $mandatory, ?string $dueAt, User $by): Assignment
    {
        abort_unless($draft->isDraft() && $module->course_id === $draft->course_id, 422);

        return DB::transaction(function () use ($draft, $module, $title, $body, $maxPoints, $mandatory, $dueAt, $by) {
            $a = Assignment::create(['course_id' => $module->course_id, 'module_id' => $module->id]);
            $this->reviseText($a, $title, $body, $dueAt, null, $by);
            DB::table('version_assignments')->insert(['course_version_id' => $draft->id, 'assignment_id' => $a->id,
                'max_points' => max(1, $maxPoints), 'is_mandatory' => $mandatory]);
            Audit::record('assignment.created', $a, after: ['max_points' => $maxPoints, 'mandatory' => $mandatory, 'version' => $draft->id], actorId: $by->id);
            Manifest::forget();

            return $a;
        });
    }

    /** Mətn/son tarix/keys faylı: yeni revizya, dərhal. Bal və məcburilik versiyadadır. */
    public function reviseText(Assignment $a, array $title, array $body, ?string $dueAt, ?string $caseFileId, User $by): AssignmentRevision
    {
        return DB::transaction(function () use ($a, $title, $body, $dueAt, $caseFileId, $by) {
            $cur = $a->current_revision_id ? AssignmentRevision::find($a->current_revision_id) : null;
            $rev = AssignmentRevision::create([
                'assignment_id' => $a->id, 'revision_no' => (int) AssignmentRevision::where('assignment_id', $a->id)->max('revision_no') + 1,
                'title' => $title, 'body' => $body, 'due_at' => $dueAt ?: null,
                'case_file_id' => $caseFileId ?? $cur?->case_file_id, 'created_by' => $by->id, 'created_at' => now(),
            ]);
            $a->update(['current_revision_id' => $rev->id]);
            $a->setRelation('revision', $rev);

            return $rev;
        });
    }

    /**
     * Maksimum bal / məcburilik dəyişikliyi (bölmə 1c-4, default): tapşırığa ən azı bir bal verilibsə,
     * dəyişiklik admin təsdiqi və məcburi səbəb tələb edir; verilmiş qiymətlər və diplomlar dəyişmir (snapshot).
     */
    public function setScoring(CourseVersion $draft, Assignment $a, int $maxPoints, bool $mandatory, User $by, ?string $reason): void
    {
        abort_unless($draft->isDraft() && $a->course_id === $draft->course_id, 422);
        DbContext::actAs($by->id); // RLS: qiymətləri dəyişikliyi edən şəxs adından görürük
        $row = DB::table('version_assignments')->where(['course_version_id' => $draft->id, 'assignment_id' => $a->id])->first();
        abort_unless($row, 404);
        $graded = DB::table('grades')->where('assignment_id', $a->id)->exists();
        $changed = (int) $row->max_points !== $maxPoints || (bool) $row->is_mandatory !== $mandatory;
        if ($changed && $graded) {
            if (! $by->isAdmin()) {
                throw ValidationException::withMessages(['max_points' => __('assess.max_points_admin_only')]);
            }
            if (trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => __('admin.reason_required')]);
            }
        }
        DB::table('version_assignments')->where(['course_version_id' => $draft->id, 'assignment_id' => $a->id])
            ->update(['max_points' => max(1, $maxPoints), 'is_mandatory' => $mandatory]);
        Audit::record('assignment.scoring_changed', $a, before: ['max_points' => $row->max_points, 'mandatory' => (bool) $row->is_mandatory],
            after: ['max_points' => $maxPoints, 'mandatory' => $mandatory, 'version' => $draft->id], reason: $reason, actorId: $by->id);
    }

    /** Versiyadakı tapşırıqlar (modul üzrə), tələbənin göndərişi və cari qiyməti ilə. */
    public function forVersion(int $versionId, ?int $moduleId = null, ?int $userId = null): Collection
    {
        $q = DB::table('version_assignments as va')->join('assignments as a', 'a.id', '=', 'va.assignment_id')
            ->join('assignment_revisions as r', 'r.id', '=', 'a.current_revision_id')
            ->where('va.course_version_id', $versionId)
            ->select('a.id', 'a.module_id', 'a.course_id', 'va.max_points', 'va.is_mandatory', 'r.title', 'r.body', 'r.due_at', 'r.case_file_id')
            ->orderBy('a.id');
        if ($moduleId) {
            $q->where('a.module_id', $moduleId);
        }
        $rows = $q->get()->map(function ($r) {
            $r->title = json_decode($r->title, true);
            $r->body = json_decode($r->body, true);

            return $r;
        });
        if ($userId) {
            $subs = DB::table('submissions')->where('user_id', $userId)->whereIn('assignment_id', $rows->pluck('id')->all() ?: [0])->get()->keyBy('assignment_id');
            $grades = DB::table('grades')->where('user_id', $userId)->whereNull('superseded_at')->whereIn('assignment_id', $rows->pluck('id')->all() ?: [0])->get()->keyBy('assignment_id');
            $rows->each(function ($r) use ($subs, $grades) {
                $r->submission = $subs[$r->id] ?? null;
                $r->grade = $grades[$r->id] ?? null;
            });
        }

        return $rows;
    }

    public function versionOf(Enrollment $e): int
    {
        return $e->course_version_id;
    }
}
