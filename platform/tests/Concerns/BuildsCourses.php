<?php

namespace Tests\Concerns;

use App\Domain\Content\VersionService;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Models\VideoAsset;
use App\Support\System;

trait BuildsCourses
{
    /** @return array{course: Course, lessons: array<int, array<int, Lesson>>} */
    protected function buildCourse(string $slug = 'marketinq-kursu', int $modules = 3, int $lessonsPer = 2, bool $video = true): array
    {
        $vs = app(VersionService::class);
        $course = Course::create(['slug' => $slug, 'external_id' => $slug, 'name' => ['az' => 'Kurs '.$slug, 'en' => 'Course '.$slug]]);
        $draft = $vs->draft($course);
        $lessons = [];
        for ($i = 1; $i <= $modules; $i++) {
            $m = $vs->addModule($draft, ['az' => "Modul $i"], null, "m$i");
            for ($j = 1; $j <= $lessonsPer; $j++) {
                $l = $vs->addLesson($draft, $m, ['az' => "Dərs $i.$j"], ['az' => "Mətn $i.$j"]);
                if ($video) {
                    $v = VideoAsset::create(['provider' => 'fake', 'provider_video_id' => "vid-$slug-$i-$j", 'status' => 'ready', 'duration_seconds' => 600]);
                    $vs->revise($l, ['video_asset_id' => $v->id]);
                }
                $lessons[$i][$j] = $l->fresh();
            }
        }
        $admin = $this->makeUser(['email' => 'pub-'.$slug.'@example.com'], ['admin']);
        $vs->publish($draft, 'new_only', null, $admin);
        if (\Schema::hasTable('prices')) {
            \DB::table('prices')->insert(['course_id' => $course->id, 'amount_qepik' => 9900, 'currency' => 'AZN', 'valid_from' => now()->subDay(), 'created_at' => now()]);
        }

        return ['course' => $course->fresh(), 'lessons' => $lessons];
    }

    protected function enroll(User $user, Course $course, ?\DateTimeInterface $paidUntil = null): Enrollment
    {
        return System::run(fn () => Enrollment::updateOrCreate(['user_id' => $user->id, 'course_id' => $course->id], [
            'course_version_id' => $course->fresh()->current_version_id, 'paid_until' => $paidUntil ?? now()->addMonth(),
        ]));
    }
}
