<?php

namespace Tests\Feature;

use App\Domain\Content\Manifest;
use App\Domain\Content\VersionService;
use App\Domain\Learning\ProgressService;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Support\DbContext;
use App\Support\System;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsCourses;
use Tests\Support\Parallel;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use BuildsCourses;

    public function test_position_saved_and_restored_and_max_watched_only_grows(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $l = $L[1][1];
        $this->signIn($u)->postJson("/platform/api/lessons/{$l->id}/progress", ['position' => 300, 'duration' => 600])->assertOk()->assertJson(['position' => 300, 'max_watched' => 300]);
        $this->postJson("/platform/api/lessons/{$l->id}/progress", ['position' => 120, 'duration' => 600])->assertOk()->assertJson(['position' => 120, 'max_watched' => 300]);
        // sendBeacon: text/plain gövdə
        $this->call('POST', "/platform/api/lessons/{$l->id}/progress", [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['position' => 130]))->assertOk();
        $this->assertSame(1, LessonProgress::where('user_id', $u->id)->count());
        $this->postJson("/platform/api/lessons/{$l->id}/playback")->assertOk()->assertJson(['resume_at' => 130]);
    }

    public function test_watching_100_percent_does_not_complete_lesson(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $this->signIn($u)->postJson("/platform/api/lessons/{$L[1][1]->id}/progress", ['position' => 600, 'duration' => 600])->assertJson(['percent' => 100]);
        $this->assertNull(LessonProgress::where('user_id', $u->id)->value('completed_at'));
    }

    public function test_complete_is_idempotent(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $first = $this->signIn($u)->postJson("/platform/api/lessons/{$L[1][1]->id}/complete")->assertOk()->json('completed_at');
        $this->travel(5)->minutes();
        $second = $this->postJson("/platform/api/lessons/{$L[1][1]->id}/complete")->assertOk()->json('completed_at');
        $this->assertSame($first, $second);
        $this->assertSame(1, LessonProgress::where('user_id', $u->id)->count());
    }

    public function test_parallel_progress_upserts_create_single_row(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $lid = $L[1][1]->id;
        $results = Parallel::run(4, function (int $i) use ($u, $c, $lid) {
            DbContext::actAs($u->id);
            app(ProgressService::class)->heartbeat($u, $c, $lid, 100 + $i * 10, 600);
            app(ProgressService::class)->complete($u, $c, $lid);

            return 'ok';
        });
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], $results);
        $this->assertSame(1, System::run(fn () => DB::table('lesson_progress')->where(['user_id' => $u->id, 'lesson_id' => $lid])->count()));
    }

    public function test_module_not_complete_until_all_lessons_and_mentoring_unlocks_then_survives_new_lesson(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 2, lessonsPer: 2);
        $u = $this->makeUser();
        $e = $this->enroll($u, $c);
        $this->signIn($u)->postJson("/platform/api/lessons/{$L[1][1]->id}/complete");
        $m = Manifest::for($c->current_version_id);
        $rows = DB::table('lesson_progress')->where('user_id', $u->id)->get()->keyBy('lesson_id');
        $this->assertFalse(app(ProgressService::class)->moduleCompleted($m, $L[1][1]->module_id, $rows));
        $this->postJson("/platform/api/lessons/{$L[1][2]->id}/complete");
        $this->postJson("/platform/api/lessons/{$L[2][1]->id}/complete");
        $this->assertNull($e->fresh()->mentoring_unlocked_at, '1:1 bütün dərslər bitmədən açılmır');
        $this->postJson("/platform/api/lessons/{$L[2][2]->id}/complete");
        $unlocked = $e->fresh()->mentoring_unlocked_at;
        $this->assertNotNull($unlocked);

        // Admin yeni dərs əlavə edir və mövcud tələbələrə də tətbiq edir → 1:1 itmir
        $vs = app(VersionService::class);
        $admin = $this->makeUser([], ['admin']);
        $draft = $vs->draft($c->fresh());
        $vs->addLesson($draft, Module::find($L[2][1]->module_id), ['az' => 'Yeni dərs']);
        $impact = $vs->impact($draft);
        $this->assertSame(1, $impact['lessons_added']);
        $this->assertSame(1, $impact['mentoring_protected']);
        $vs->publish($draft, 'all', 'Yeni mövzu əlavə olundu', $admin);
        $e->refresh();
        $this->assertSame($draft->id, $e->course_version_id);
        $this->assertEquals($unlocked, $e->mentoring_unlocked_at);
    }

    public function test_publish_to_all_requires_reason_and_new_only_keeps_old_version(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $u = $this->makeUser();
        $e = $this->enroll($u, $c);
        $vs = app(VersionService::class);
        $admin = $this->makeUser([], ['admin']);
        $draft = $vs->draft($c->fresh());
        try {
            $vs->publish($draft, 'all', '', $admin);
            $this->fail('səbəbsiz "all" qadağandır');
        } catch (ValidationException) {
        }
        $old = $e->course_version_id;
        $vs->publish($draft->fresh(), 'new_only', null, $admin);
        $this->assertSame($old, $e->fresh()->course_version_id);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'content.version_published')->where('entity_id', $draft->id)->count());
    }

    public function test_lesson_revision_keeps_history(): void
    {
        ['lessons' => $L] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $vs = app(VersionService::class);
        $vs->revise($L[1][1], ['title' => ['az' => 'Yeni başlıq']], null, 'typo');
        $this->assertSame(3, $L[1][1]->revisions()->count()); // yaradılış + video + düzəliş
        $this->assertSame('Yeni başlıq', $L[1][1]->fresh()->label());
        // Regressiya: eyni obyektlə ardıcıl revizyalar başlığı itirmir
        $l = $L[1][1]->fresh();
        $vs->revise($l, ['body' => ['az' => 'yeni mətn']]);
        $vs->revise($l, ['chapters' => []]);
        $this->assertSame('Yeni başlıq', $l->fresh()->label());
    }
}
