<?php

namespace App\Domain\Content;

use App\Models\CourseVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kurs versiyasının manifesti: sıralı modullar və onların dərsləri (cari revizya ilə).
 * Bütün icazə qərarları bu strukturdan istifadə edir (hansı dərs hansı versiyaya aiddir).
 */
final class Manifest
{
    /** @var array<int, self> */
    private static array $cache = [];

    /** @param Collection<int, object> $modules */
    private function __construct(public readonly int $versionId, public readonly Collection $modules, public readonly Collection $lessons) {}

    public static function for(CourseVersion|int $version): self
    {
        $id = is_int($version) ? $version : $version->id;
        if (app()->runningUnitTests()) {
            self::$cache = [];
        }

        return self::$cache[$id] ??= self::load($id);
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function load(int $versionId): self
    {
        $modules = collect(DB::select(
            'SELECT m.id, m.title, m.description, vm.position, vm.demo_open FROM version_modules vm
             JOIN modules m ON m.id = vm.module_id WHERE vm.course_version_id = ? ORDER BY vm.position', [$versionId]
        ))->map(function ($m) {
            $m->title = json_decode($m->title, true);
            $m->description = $m->description ? json_decode($m->description, true) : null;

            return $m;
        });
        $lessons = collect(DB::select(
            'SELECT l.id, vl.module_id, vl.position, r.title, r.video_asset_id, r.chapters, va.duration_seconds, va.status AS video_status
             FROM version_lessons vl JOIN lessons l ON l.id = vl.lesson_id
             LEFT JOIN lesson_revisions r ON r.id = l.current_revision_id
             LEFT JOIN video_assets va ON va.id = r.video_asset_id
             WHERE vl.course_version_id = ? ORDER BY vl.module_id, vl.position', [$versionId]
        ))->map(function ($l) {
            $l->title = $l->title ? json_decode($l->title, true) : [];
            $l->chapters = $l->chapters ? json_decode($l->chapters, true) : [];

            return $l;
        });
        // Dərsləri modul sırasına görə düz siyahıya düz
        $order = $modules->pluck('position', 'id');
        $lessons = $lessons->sortBy(fn ($l) => sprintf('%05d-%05d', $order[$l->module_id] ?? 99999, $l->position))->values();

        return new self($versionId, $modules, $lessons);
    }

    public function hasLesson(int $lessonId): bool
    {
        return $this->lessons->contains('id', $lessonId);
    }

    public function lessonIds(): array
    {
        return $this->lessons->pluck('id')->all();
    }

    public function moduleOf(int $lessonId): ?object
    {
        $l = $this->lessons->firstWhere('id', $lessonId);

        return $l ? $this->modules->firstWhere('id', $l->module_id) : null;
    }

    public function lessonsOf(int $moduleId): Collection
    {
        return $this->lessons->where('module_id', $moduleId)->values();
    }

    /** @return int[] demo üçün açıq modulların ID-ləri */
    public function demoModuleIds(): array
    {
        return $this->modules->where('demo_open', true)->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    public function next(int $lessonId): ?object
    {
        $i = $this->lessons->search(fn ($l) => $l->id === $lessonId);

        return $i === false ? null : $this->lessons->get($i + 1);
    }
}
