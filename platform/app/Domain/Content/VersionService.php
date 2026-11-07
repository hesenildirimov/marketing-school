<?php

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Models\Course;
use App\Models\CourseVersion;
use App\Models\Lesson;
use App\Models\LessonRevision;
use App\Models\Module;
use App\Models\User;
use App\Support\DbContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kurs strukturunun versiyalaşdırılması (bölmə 3.4).
 * Struktur dəyişiklikləri (modul/dərs/məcburi tapşırıq əlavə/silmə, sıra, demo modulları) qaralama versiyada edilir,
 * "dərc et" zamanı admin təsir görünüşünü görür və tətbiq rejimini seçir: yalnız yeni enrollment-lər və ya hamı (səbəb məcburi).
 */
final class VersionService
{
    public function draft(Course $course): CourseVersion
    {
        return DB::transaction(function () use ($course) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('draft:'.$course->id)]);
            if ($d = $course->draft()) {
                return $d;
            }
            $no = (int) $course->versions()->max('version_no') + 1;
            $draft = $course->versions()->create(['version_no' => $no, 'status' => 'draft']);
            if ($course->current_version_id) {
                DB::insert('INSERT INTO version_modules (course_version_id, module_id, position, demo_open)
                    SELECT ?, module_id, position, demo_open FROM version_modules WHERE course_version_id = ?', [$draft->id, $course->current_version_id]);
                DB::insert('INSERT INTO version_lessons (course_version_id, lesson_id, module_id, position)
                    SELECT ?, lesson_id, module_id, position FROM version_lessons WHERE course_version_id = ?', [$draft->id, $course->current_version_id]);
                if (\Schema::hasTable('version_assignments')) {
                    DB::insert('INSERT INTO version_assignments (course_version_id, assignment_id, max_points, is_mandatory)
                        SELECT ?, assignment_id, max_points, is_mandatory FROM version_assignments WHERE course_version_id = ?', [$draft->id, $course->current_version_id]);
                }
            }
            Manifest::forget();

            return $draft;
        });
    }

    public function addModule(CourseVersion $draft, array $title, ?array $description = null, ?string $key = null): Module
    {
        $this->assertDraft($draft);

        return DB::transaction(function () use ($draft, $title, $description, $key) {
            $m = Module::create(['course_id' => $draft->course_id, 'external_key' => $key ?? 'm-'.bin2hex(random_bytes(4)),
                'title' => $title, 'description' => $description]);
            $pos = (int) DB::table('version_modules')->where('course_version_id', $draft->id)->max('position') + 1;
            DB::table('version_modules')->insert(['course_version_id' => $draft->id, 'module_id' => $m->id, 'position' => $pos,
                'demo_open' => $pos <= $draft->course->demo_module_count]);
            Manifest::forget();

            return $m;
        });
    }

    public function addLesson(CourseVersion $draft, Module $module, array $title, ?array $body = null, ?User $by = null): Lesson
    {
        $this->assertDraft($draft);
        abort_unless($module->course_id === $draft->course_id, 422);

        return DB::transaction(function () use ($draft, $module, $title, $body, $by) {
            $lesson = Lesson::create(['course_id' => $module->course_id, 'module_id' => $module->id]);
            $this->revise($lesson, ['title' => $title, 'body' => $body], $by);
            $pos = (int) DB::table('version_lessons')->where(['course_version_id' => $draft->id, 'module_id' => $module->id])->max('position') + 1;
            DB::table('version_lessons')->insert(['course_version_id' => $draft->id, 'lesson_id' => $lesson->id, 'module_id' => $module->id, 'position' => $pos]);
            Manifest::forget();

            return $lesson;
        });
    }

    /** Məzmun dəyişikliyi: yeni revizya, köhnələr saxlanılır (versiya dəyişmir). */
    public function revise(Lesson $lesson, array $changes, ?User $by = null, ?string $note = null): LessonRevision
    {
        return DB::transaction(function () use ($lesson, $changes, $by, $note) {
            // Relation keşinə etibar edilmir: əvvəlki revizya həmişə DB-dən oxunur (əks halda başlıq itə bilər)
            $cur = $lesson->current_revision_id ? LessonRevision::find($lesson->current_revision_id) : null;
            $rev = LessonRevision::create([
                'lesson_id' => $lesson->id,
                'revision_no' => (int) LessonRevision::where('lesson_id', $lesson->id)->max('revision_no') + 1,
                'title' => $changes['title'] ?? $cur?->title ?? ['az' => ''],
                'body' => array_key_exists('body', $changes) ? $changes['body'] : $cur?->body,
                'video_asset_id' => array_key_exists('video_asset_id', $changes) ? $changes['video_asset_id'] : $cur?->video_asset_id,
                'chapters' => array_key_exists('chapters', $changes) ? $changes['chapters'] : $cur?->chapters,
                'materials' => array_key_exists('materials', $changes) ? $changes['materials'] : $cur?->materials,
                'created_by' => $by?->id, 'change_note' => $note, 'created_at' => now(),
            ]);
            $lesson->update(['current_revision_id' => $rev->id]);
            $lesson->setRelation('revision', $rev);
            if ($cur) {
                Audit::record('content.lesson_revised', $lesson, before: ['revision' => $cur->revision_no], after: ['revision' => $rev->revision_no], reason: $note);
            }
            Manifest::forget();

            return $rev;
        });
    }

    public function removeLesson(CourseVersion $draft, int $lessonId): void
    {
        $this->assertDraft($draft);
        DB::table('version_lessons')->where(['course_version_id' => $draft->id, 'lesson_id' => $lessonId])->delete();
        Manifest::forget();
    }

    public function moveModule(CourseVersion $draft, int $moduleId, int $dir): void
    {
        $this->assertDraft($draft);
        $this->swap('version_modules', ['course_version_id' => $draft->id], 'module_id', $moduleId, $dir);
    }

    public function moveLesson(CourseVersion $draft, int $lessonId, int $dir): void
    {
        $this->assertDraft($draft);
        $mid = DB::table('version_lessons')->where(['course_version_id' => $draft->id, 'lesson_id' => $lessonId])->value('module_id');
        $this->swap('version_lessons', ['course_version_id' => $draft->id, 'module_id' => $mid], 'lesson_id', $lessonId, $dir);
    }

    public function setDemoOpen(CourseVersion $draft, int $moduleId, bool $open): void
    {
        $this->assertDraft($draft);
        DB::table('version_modules')->where(['course_version_id' => $draft->id, 'module_id' => $moduleId])->update(['demo_open' => $open]);
        Manifest::forget();
    }

    /**
     * Təsir görünüşü: neçə tələbə təsirlənir, kimin 1:1/diplom hüququ dəyişə bilər.
     * Artıq açılmış 1:1 və verilmiş diplom heç vaxt itmir — onlar ayrıca göstərilir.
     */
    public function impact(CourseVersion $draft): array
    {
        $course = $draft->course;
        $cur = $course->current_version_id;
        $old = $cur ? Manifest::for($cur) : null;
        $new = Manifest::for($draft->id);
        $added = array_values(array_diff($new->lessonIds(), $old?->lessonIds() ?? []));
        $removed = array_values(array_diff($old?->lessonIds() ?? [], $new->lessonIds()));
        $enr = DB::table('enrollments')->where('course_id', $course->id)->whereNull('canceled_at');
        $total = (clone $enr)->count();
        $unlocked = (clone $enr)->whereNotNull('mentoring_unlocked_at')->count();
        $certs = \Schema::hasTable('certificates') ? DB::table('certificates')->where('course_id', $course->id)->whereNull('revoked_at')->count() : 0;
        $mand = $this->mandatoryDiff($cur, $draft->id);

        return [
            'enrollments' => $total,
            'lessons_added' => count($added),
            'lessons_removed' => count($removed),
            'mandatory_assignments_added' => $mand['added'],
            'max_points_changed' => $mand['changed'],
            // tamamlanmamış tələbələr üçün 1:1 açılması gecikə bilər; açılmışlar qorunur
            'mentoring_may_be_delayed' => count($added) > 0 ? $total - $unlocked : 0,
            'mentoring_protected' => $unlocked,
            'diploma_may_change' => ($mand['added'] || $mand['changed']) ? max(0, $total - $certs) : 0,
            'diplomas_protected' => $certs,
        ];
    }

    public function publish(CourseVersion $draft, string $applyMode, ?string $reason, User $by): CourseVersion
    {
        $this->assertDraft($draft);
        if (! in_array($applyMode, ['new_only', 'all'], true)) {
            throw ValidationException::withMessages(['apply_mode' => 'invalid']);
        }
        if ($applyMode === 'all' && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => __('admin.reason_required')]);
        }

        // Admin adından (RLS: enrollment-lərin köçürülməsi yalnız admin kontekstində görünür)
        return DbContext::as($by->id, fn () => DB::transaction(function () use ($draft, $applyMode, $reason, $by) {
            $course = Course::lockForUpdate()->find($draft->course_id);
            $impact = $this->impact($draft);
            $prev = $course->current_version_id;
            $draft->update(['status' => 'published', 'published_at' => now(), 'published_by' => $by->id,
                'apply_mode' => $applyMode, 'change_reason' => $reason]);
            $course->update(['current_version_id' => $draft->id]);
            $moved = 0;
            if ($applyMode === 'all' && $prev) {
                $moved = DB::table('enrollments')->where('course_id', $course->id)->where('course_version_id', $prev)
                    ->update(['course_version_id' => $draft->id, 'updated_at' => now()]);
            }
            Audit::record('content.version_published', $draft, before: ['version_id' => $prev],
                after: ['version_id' => $draft->id, 'apply_mode' => $applyMode, 'moved_enrollments' => $moved, 'impact' => $impact], reason: $reason, actorId: $by->id);
            Manifest::forget();

            return $draft;
        }));
    }

    private function mandatoryDiff(?int $from, int $to): array
    {
        if (! \Schema::hasTable('version_assignments')) {
            return ['added' => 0, 'changed' => 0];
        }
        $q = fn ($v) => $v ? DB::table('version_assignments')->where('course_version_id', $v)->where('is_mandatory', true)
            ->pluck('max_points', 'assignment_id') : collect();
        $a = $q($from);
        $b = $q($to);

        return ['added' => $b->keys()->diff($a->keys())->count(),
            'changed' => $b->filter(fn ($p, $id) => isset($a[$id]) && (int) $a[$id] !== (int) $p)->count()];
    }

    private function swap(string $table, array $scope, string $col, int $id, int $dir): void
    {
        DB::transaction(function () use ($table, $scope, $col, $id, $dir) {
            $rows = DB::table($table)->where($scope)->orderBy('position')->get()->values();
            $i = $rows->search(fn ($r) => (int) $r->$col === $id);
            $j = $i === false ? false : $i + ($dir < 0 ? -1 : 1);
            if ($i === false || $j < 0 || $j >= $rows->count()) {
                return;
            }
            [$a, $b] = [$rows[$i], $rows[$j]];
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table($table)->where($scope)->where($col, $a->$col)->update(['position' => $b->position]);
            DB::table($table)->where($scope)->where($col, $b->$col)->update(['position' => $a->position]);
        });
        Manifest::forget();
    }

    private function assertDraft(CourseVersion $v): void
    {
        abort_unless($v->isDraft(), 422, 'Only draft versions can be changed');
    }
}
