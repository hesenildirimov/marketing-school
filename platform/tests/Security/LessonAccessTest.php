<?php

namespace Tests\Security;

use App\Domain\Content\VersionService;
use App\Domain\Video\BunnyStreamProvider;
use App\Models\Module;
use App\Models\PersonalNote;
use App\Support\DbContext;
use App\Support\System;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsCourses;
use Tests\TestCase;

class LessonAccessTest extends TestCase
{
    use BuildsCourses;

    public function test_user_without_enrollment_gets_nothing(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->signIn($u);
        $this->get("/platform/courses/{$c->slug}")->assertStatus(403)->assertSee(__('course.buy_title'));
        $this->get("/platform/courses/{$c->slug}/lessons/{$L[1][1]->id}")->assertStatus(403)->assertDontSee('data-playback');
        $this->postJson("/platform/api/lessons/{$L[1][1]->id}/playback")->assertStatus(403);
        $this->postJson("/platform/api/lessons/{$L[1][1]->id}/progress", ['position' => 1])->assertStatus(403);
        $this->postJson("/platform/api/lessons/{$L[1][1]->id}/complete")->assertStatus(403);
        $this->getJson("/platform/api/lessons/{$L[1][1]->id}/notes")->assertStatus(403);
    }

    public function test_payment_for_one_course_does_not_open_the_other(): void
    {
        ['course' => $a] = $this->buildCourse('marketinq-kursu');
        ['course' => $b, 'lessons' => $LB] = $this->buildCourse('digital-marketinq-kursu');
        $u = $this->makeUser();
        $this->enroll($u, $a);
        $this->signIn($u)->postJson("/platform/api/lessons/{$LB[1][1]->id}/playback")->assertStatus(403);
        $this->get("/platform/courses/{$b->slug}/lessons/{$LB[1][1]->id}")->assertStatus(403);
    }

    public function test_active_and_past_due_have_access_expired_and_canceled_do_not(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $e = $this->enroll($u, $c, now()->addDay());
        $url = "/platform/api/lessons/{$L[3][1]->id}/playback";
        $this->signIn($u)->postJson($url)->assertOk();
        $this->travel(2)->days();     // past_due (grace 3 gün)
        $this->postJson($url)->assertOk();
        $this->travel(3)->days();     // grace bitdi → expired
        $this->postJson($url)->assertStatus(403);
        $this->travelBack();
        System::run(fn () => $e->update(['paid_until' => now()->addMonth(), 'canceled_at' => now()]));
        $this->postJson($url)->assertStatus(403);
    }

    public function test_token_ttl_is_short_and_capped_by_grace_until(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->enroll($u, $c, now()->subDays(3)->addMinutes(3)); // grace_until = now + 3 dəq
        $r = $this->signIn($u)->postJson("/platform/api/lessons/{$L[1][1]->id}/playback")->assertOk();
        $exp = Carbon::parse($r->json('expires_at'));
        $this->assertTrue($exp->lte(now()->addMinutes(3)->addSecond()), 'token grace_until-dən uzun ola bilməz');

        $this->enroll($u, $c, now()->addMonth());
        $r = $this->postJson("/platform/api/lessons/{$L[1][1]->id}/playback")->assertOk();
        $this->assertTrue(Carbon::parse($r->json('expires_at'))->lte(now()->addMinutes(10)->addSecond()));
        $this->assertSame($u->short_code, $r->json('watermark.code'));
        $this->assertArrayNotHasKey('phone', $r->json('watermark'));
    }

    public function test_refresh_rechecks_access(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $e = $this->enroll($u, $c);
        $url = "/platform/api/lessons/{$L[1][1]->id}/playback";
        $this->signIn($u)->postJson($url)->assertOk();
        System::run(fn () => $e->update(['canceled_at' => now()]));
        $this->postJson($url, ['kind' => 'refresh'])->assertStatus(403);
        $this->assertSame(1, DB::table('video_token_log')->where('kind', 'denied')->count());
    }

    public function test_signed_url_format_and_no_direct_mp4(): void
    {
        $url = BunnyStreamProvider::signDirectoryUrl('vz-x.b-cdn.net', '/abc/playlist.m3u8', '/abc/', 'secret', 1700000000);
        $expected = rtrim(strtr(base64_encode(hash('sha256', 'secret/abc/1700000000token_path=/abc/', true)), '+/', '-_'), '=');
        $this->assertSame("https://vz-x.b-cdn.net/bcdn_token={$expected}&expires=1700000000&token_path=%2Fabc%2F/abc/playlist.m3u8", $url);
        $this->assertStringNotContainsString('.mp4', $url);
        // Token istifadəçiyə və vaxta bağlıdır: fərqli vaxt → fərqli imza
        $this->assertNotSame($url, BunnyStreamProvider::signDirectoryUrl('vz-x.b-cdn.net', '/abc/playlist.m3u8', '/abc/', 'secret', 1700000001));
    }

    public function test_lesson_in_newer_version_not_visible_to_old_enrollment(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $vs = app(VersionService::class);
        $draft = $vs->draft($c->fresh());
        $new = $vs->addLesson($draft, Module::find($L[1][1]->module_id), ['az' => 'Yeni']);
        $vs->publish($draft, 'new_only', null, $this->makeUser([], ['admin']));
        $this->signIn($u)->postJson("/platform/api/lessons/{$new->id}/progress", ['position' => 1])->assertStatus(403);
    }

    public function test_instructor_sees_only_own_course(): void
    {
        ['course' => $a] = $this->buildCourse('marketinq-kursu');
        ['course' => $b, 'lessons' => $LB] = $this->buildCourse('digital-marketinq-kursu');
        $t = $this->makeUser([], ['instructor']);
        DB::table('course_instructors')->insert(['course_id' => $a->id, 'user_id' => $t->id]);
        $this->signIn($t)->get("/platform/admin/courses/{$a->slug}")->assertOk();
        $this->get("/platform/admin/courses/{$b->slug}")->assertStatus(403);
        $this->postJson("/platform/api/lessons/{$LB[1][1]->id}/playback")->assertStatus(403);
        $this->post("/platform/admin/courses/{$a->slug}/publish", ['apply_mode' => 'new_only'])->assertStatus(403);
    }

    public function test_notes_are_private_even_from_instructor_and_rls(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->enroll($a, $c);
        $this->enroll($b, $c);
        $lid = $L[1][1]->id;
        $this->signIn($a)->postJson("/platform/api/lessons/{$lid}/notes", ['body' => '<script>alert(1)</script> gizli'])->assertCreated();
        $noteId = PersonalNote::first()->id;
        $this->get("/platform/courses/{$c->slug}/lessons/{$lid}")->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

        $this->signIn($b)->getJson("/platform/api/lessons/{$lid}/notes")->assertOk()->assertJsonCount(0);
        $this->deleteJson("/platform/api/lessons/{$lid}/notes/{$noteId}")->assertNotFound();

        $t = $this->makeUser([], ['instructor']);
        DB::table('course_instructors')->insert(['course_id' => $c->id, 'user_id' => $t->id]);
        DbContext::actAs($t->id);
        $this->assertSame(0, DB::table('personal_notes')->count(), 'RLS: təlimçi şəxsi qeydləri görmür');
        DbContext::actAs($b->id);
        $this->assertSame(0, DB::table('personal_notes')->count());
        $this->assertSame(0, DB::table('lesson_progress')->where('user_id', $a->id)->count());
        DbContext::actAs($a->id);
        $this->assertSame(1, DB::table('personal_notes')->count());
    }

    public function test_rls_enrollments_and_progress_scoped_by_user_and_course_staff(): void
    {
        ['course' => $c] = $this->buildCourse();
        ['course' => $d] = $this->buildCourse('digital-marketinq-kursu');
        $a = $this->makeUser();
        $this->enroll($a, $c);
        $this->enroll($a, $d);
        $t = $this->makeUser([], ['instructor']);
        DB::table('course_instructors')->insert(['course_id' => $c->id, 'user_id' => $t->id]);
        DbContext::actAs($t->id);
        $this->assertSame([$c->id], DB::table('enrollments')->pluck('course_id')->all(), 'təlimçi yalnız öz kursunun enrollment-lərini görür');
        DbContext::actAs($this->makeUser()->id);
        $this->assertSame(0, DB::table('enrollments')->count());
        // Tələbə özü üçün enrollment yarada bilməz? RLS yalnız öz user_id-sinə icazə verir; başqası üçün yox:
        $this->expectException(QueryException::class);
        DB::table('enrollments')->insert(['user_id' => $a->id, 'course_id' => $c->id, 'course_version_id' => $c->current_version_id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_locked_lesson_page_does_not_leak_content(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $vs = app(VersionService::class);
        $draft = $vs->draft($c->fresh());
        $hidden = $vs->addLesson($draft, Module::find($L[1][1]->module_id), ['az' => 'Gizli dərs'], ['az' => 'GİZLİ MƏTN']);
        $vs->publish($draft, 'new_only', null, $this->makeUser([], ['admin']));
        $this->signIn($u)->get("/platform/courses/{$c->slug}/lessons/{$hidden->id}")->assertNotFound();
    }

    public function test_lesson_page_layout_for_paid_student(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse();
        $u = $this->makeUser();
        $this->enroll($u, $c);
        $this->signIn($u)->get("/platform/courses/{$c->slug}/lessons/{$L[1][1]->id}")->assertOk()
            ->assertSee('data-playback', false)->assertSee(__('course.tab_homework'))->assertSee(__('course.tab_materials'))
            ->assertSee(__('course.tab_notes'))->assertSee(__('course.complete_btn'))->assertSee(__('course.next'))
            ->assertSee('controlslist="nodownload', false)->assertSee(__('course.video_lang_note'));
    }
}
