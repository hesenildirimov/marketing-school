<?php

namespace Tests\Feature;

use App\Domain\Content\Manifest;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\VideoAsset;
use Tests\Concerns\BuildsCourses;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use BuildsCourses;

    public function test_admin_manages_structure_through_draft_and_publishes(): void
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 2, lessonsPer: 1);
        $admin = $this->makeUser([], ['admin']);
        $this->signIn($admin);
        $this->get('/platform/admin/courses')->assertOk()->assertSee($c->title());
        $this->get("/platform/admin/courses/{$c->slug}")->assertOk()->assertSee(__('admin.create_draft'));
        $this->post("/platform/admin/courses/{$c->slug}/draft")->assertRedirect();
        $this->get("/platform/admin/courses/{$c->slug}")->assertOk()->assertSee(__('admin.impact'));
        $this->post("/platform/admin/courses/{$c->slug}/modules", ['title_az' => 'Yeni modul', 'title_en' => 'New module'])->assertRedirect();
        $mid = Module::where('course_id', $c->id)->latest('id')->value('id');
        $this->post("/platform/admin/courses/{$c->slug}/modules/{$mid}/lessons", ['title_az' => 'Yeni dərs'])->assertRedirect();
        $lesson = Lesson::latest('id')->first();
        $this->put("/platform/admin/courses/{$c->slug}/lessons/{$lesson->id}", [
            'title_az' => 'Yeni dərs', 'body_az' => 'Mətn', 'chapters' => "00:00 Giriş\n01:30 Əsas hissə\nbad line",
            'materials' => "Şablon | https://example.com/a.pdf\nPis | javascript:alert(1)\nHttp | http://insecure.example",
        ])->assertRedirect();
        $rev = $lesson->fresh()->revision;
        $this->assertEquals([['t' => 0, 'title' => ['az' => 'Giriş']], ['t' => 90, 'title' => ['az' => 'Əsas hissə']]], $rev->chapters);
        $this->assertEquals([['title' => 'Şablon', 'url' => 'https://example.com/a.pdf']], $rev->materials, 'yalnız https linkləri');
        $this->postJson("/platform/admin/courses/{$c->slug}/lessons/{$lesson->id}/video")->assertOk()->assertJsonStructure(['endpoint', 'headers']);
        $this->assertSame('uploading', VideoAsset::latest('id')->value('status'));
        $this->post("/platform/admin/courses/{$c->slug}/lessons/{$lesson->id}/video/refresh")->assertRedirect();
        $this->assertSame('ready', VideoAsset::latest('id')->value('status'));
        $this->get("/platform/admin/courses/{$c->slug}/lessons/{$lesson->id}")->assertOk()->assertSee('tus-upload.js');
        $this->post("/platform/admin/courses/{$c->slug}/modules/{$mid}/action", ['action' => 'up'])->assertRedirect();
        $this->post("/platform/admin/courses/{$c->slug}/publish", ['apply_mode' => 'new_only'])->assertRedirect();
        $this->assertSame(2, $c->fresh()->currentVersion->version_no);
        $this->assertCount(3, Manifest::for($c->fresh()->current_version_id)->modules);
    }

    public function test_publish_requires_recent_password_confirmation(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $admin = $this->makeUser([], ['admin']);
        $this->signIn($admin)->post("/platform/admin/courses/{$c->slug}/draft");
        $this->travel(20)->minutes();
        $this->get('/platform/admin'); // aktivlik (idle limitdən az)
        $this->post("/platform/admin/courses/{$c->slug}/publish", ['apply_mode' => 'new_only'])->assertRedirect(route('reauth'));
        $this->post('/platform/reauth', ['password' => 'Secret12345'])->assertRedirect();
        $this->post("/platform/admin/courses/{$c->slug}/publish", ['apply_mode' => 'new_only'])->assertRedirect();
        $this->assertSame(2, $c->fresh()->currentVersion->version_no);
    }

    public function test_student_cannot_reach_admin(): void
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $this->signIn($this->makeUser())->get("/platform/admin/courses/{$c->slug}")->assertStatus(403);
        $this->post("/platform/admin/courses/{$c->slug}/draft")->assertStatus(403);
    }
}
