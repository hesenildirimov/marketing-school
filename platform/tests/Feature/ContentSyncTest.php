<?php

namespace Tests\Feature;

use App\Domain\Content\CourseSync;
use App\Domain\Content\Manifest;
use App\Models\Course;
use App\Models\Module;
use App\Support\System;
use Tests\TestCase;

class ContentSyncTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $root = sys_get_temp_dir().'/landing-'.bin2hex(random_bytes(4));
        $this->dir = $root.'/kurslar';
        mkdir($this->dir, 0777, true);
        foreach (glob(base_path('../src/_data/kurslar/*.json')) as $f) {
            copy($f, $this->dir.'/'.basename($f));
        }
        foreach (['en', 'ru'] as $l) {
            mkdir("$root/i18n/$l/kurslar", 0777, true);
            foreach (glob(base_path("../src/_data/i18n/$l/kurslar/*.json")) as $f) {
                copy($f, "$root/i18n/$l/kurslar/".basename($f));
            }
        }
    }

    public function test_import_is_idempotent_and_creates_published_version_with_demo_modules(): void
    {
        $sync = app(CourseSync::class);
        $first = System::run(fn () => $sync->import($this->dir));
        $this->assertSame(['courses_created' => 2, 'modules_created' => 19], $first);
        $second = System::run(fn () => $sync->import($this->dir));
        $this->assertSame(['courses_created' => 0, 'modules_created' => 0], $second);
        $this->assertSame(2, Course::count());
        $this->assertSame(19, Module::count());

        $mk = Course::where('slug', 'marketinq-kursu')->first();
        $m = Manifest::for($mk->current_version_id);
        $this->assertCount(9, $m->modules);
        $this->assertCount(2, $m->demoModuleIds(), 'default: ilk 2 modul demoda açıqdır');
        $this->assertSame('Marketinqin əsasları', $m->modules->first()->title['az']);
        $this->assertSame(10, Manifest::for(Course::where('slug', 'digital-marketinq-kursu')->value('current_version_id'))->modules->count());
        // EN/RU adlar landing tərcümələrindən gəlir
        $this->assertSame('Marketing Course', $mk->name['en']);
        $this->assertSame('Курс маркетинга', $mk->name['ru']);
        $this->assertSame('Marketing fundamentals', $m->modules->first()->title['en']);
    }

    public function test_import_does_not_overwrite_platform_edits(): void
    {
        $sync = app(CourseSync::class);
        System::run(fn () => $sync->import($this->dir));
        $cid = Course::where('slug', 'marketinq-kursu')->value('id');
        Module::where(['external_key' => 'm1', 'course_id' => $cid])->first()->update(['title' => ['az' => 'Platformada dəyişdirilib']]);
        System::run(fn () => $sync->import($this->dir));
        $this->assertSame('Platformada dəyişdirilib', Module::where(['external_key' => 'm1', 'course_id' => $cid])->first()->title['az']);
    }

    public function test_export_writes_only_owned_fields_and_check_detects_drift(): void
    {
        $sync = app(CourseSync::class);
        System::run(fn () => $sync->import($this->dir));
        $file = $this->dir.'/1-marketinq.json';
        $before = json_decode(file_get_contents($file), true);
        $this->assertSame([], System::run(fn () => $sync->export($this->dir, false)), 'importdan dərhal sonra fərq yoxdur');

        $mod = Module::where('external_key', 'm2')->where('course_id', Course::where('slug', 'marketinq-kursu')->value('id'))->first();
        $mod->update(['title' => ['az' => 'Yeni ad']]);
        Manifest::forget();
        $this->artisan('platform:courses:export', ['--path' => $this->dir, '--check' => true])->assertFailed();
        $this->artisan('platform:courses:export', ['--path' => $this->dir])->assertSuccessful();
        $after = json_decode(file_get_contents($file), true);
        $this->assertSame('Yeni ad', $after['modules'][1]['title']);
        // EN tərcüməsi platformada dəyişəndə landing EN faylı da yenilənir
        $mod->update(['title' => ['az' => 'Yeni ad', 'en' => 'New name', 'ru' => 'Новое имя']]);
        Manifest::forget();
        $this->artisan('platform:courses:export', ['--path' => $this->dir])->assertSuccessful();
        $en = json_decode(file_get_contents(dirname($this->dir).'/i18n/en/kurslar/1-marketinq.json'), true);
        $this->assertSame('New name', $en['modules'][1]['title']);
        $this->assertSame($before['seo_title'], json_decode(file_get_contents($file), true)['seo_title']);
        $this->assertSame($before['seo_title'], $after['seo_title'], 'landing-ə məxsus sahələrə toxunulmur');
        $this->assertSame($before['instructor'], $after['instructor']);
        $this->artisan('platform:courses:export', ['--path' => $this->dir, '--check' => true])->assertSuccessful();
    }
}
