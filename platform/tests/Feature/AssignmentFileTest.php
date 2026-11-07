<?php

namespace Tests\Feature;

use App\Domain\Files\FileService;
use App\Domain\Files\Scanner;
use App\Domain\Notifications\Outbox;
use App\Http\Controllers\FileController;
use App\Models\AppNotification;
use App\Models\DemoClaim;
use App\Models\OutboxEvent;
use App\Models\Submission;
use App\Models\UploadedFile as UF;
use App\Support\DbContext;
use App\Support\System;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsAssignments;
use Tests\Concerns\BuildsCourses;
use Tests\TestCase;
use Tests\Unit\FileValidatorTest;

class AssignmentFileTest extends TestCase
{
    use BuildsAssignments, BuildsCourses;

    private const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    private function pdf(string $name = 'cavab.pdf', string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n{$extra}\n%%EOF");
    }

    private function setupCourse(): array
    {
        ['course' => $c, 'lessons' => $L] = $this->buildCourse(modules: 2, lessonsPer: 1);
        [$a] = $this->addAssignments($c, [[10, true]]);
        $s = $this->makeUser();
        $this->enroll($s, $c);

        return [$c, $L, $a, $s];
    }

    public function test_paid_student_submits_text_and_file_which_stays_pending_until_scanned(): void
    {
        [$c, $L, $a, $s] = $this->setupCourse();
        $this->signIn($s)->post("/platform/assignments/{$a->id}/submit", ['body' => "Mənim cavabım\n\n<script>alert(1)</script>", 'files' => [$this->pdf()]])
            ->assertSessionHas('status', __('assess.submitted'));
        $sub = Submission::firstOrFail();
        $this->assertStringNotContainsString('<script>', $sub->body_html);
        $f = UF::firstOrFail();
        $this->assertSame('pending', $f->scan_status);
        $this->assertStringNotContainsString('cavab', $f->storage_key, 'fayl adı randomlaşdırılır');
        $this->assertStringEndsWith('.bin', $f->storage_key);
        $this->get(FileController::link($f))->assertStatus(423); // skan bitməyib → endirilmir
        Outbox::process();
        $this->assertSame('clean', $f->fresh()->scan_status, 'real clamd ilə skan');
        $this->get(FileController::link($f->fresh()))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition');
    }

    public function test_eicar_is_quarantined_owner_notified_and_never_downloadable(): void
    {
        [$c, $L, $a, $s] = $this->setupCourse();
        $id = (string) Str::uuid();
        Storage::disk('private')->put("files/xx/{$id}.bin", self::EICAR);
        System::run(fn () => UF::create(['id' => $id, 'owner_user_id' => $s->id, 'course_id' => $c->id, 'purpose' => 'submission',
            'storage_key' => "files/xx/{$id}.bin", 'original_name' => 'eicar.pdf', 'mime_detected' => 'application/pdf', 'size_bytes' => 68,
            'sha256' => hash('sha256', self::EICAR), 'scan_status' => 'pending']));
        app(FileService::class)->scan($id);
        $f = System::run(fn () => UF::find($id));
        $this->assertSame('infected', $f->scan_status);
        $this->assertNotNull($f->quarantined_at);
        $this->assertStringStartsWith('quarantine/', $f->storage_key);
        Storage::disk('private')->assertMissing("files/xx/{$id}.bin");
        $this->assertSame(1, System::run(fn () => AppNotification::where('user_id', $s->id)->where('event_type', 'file_quarantined')->where('channel', 'email')->count()));
        $t = $this->instructorFor($c);
        $this->signIn($t)->get(FileController::link($f))->assertStatus(423);
    }

    public function test_scanner_unavailable_fails_closed(): void
    {
        [$c, $L, $a, $s] = $this->setupCourse();
        config(['services.clamav.port' => 1]);
        app()->forgetInstance(Scanner::class);
        app()->forgetInstance(FileService::class);
        $this->signIn($s)->post("/platform/assignments/{$a->id}/submit", ['body' => 'x', 'files' => [$this->pdf()]]);
        Outbox::process();
        $f = UF::firstOrFail();
        $this->assertContains($f->scan_status, ['pending', 'error'], 'skan olunmayıb');
        $this->get(FileController::link($f))->assertStatus(423);
        $ev = System::run(fn () => OutboxEvent::whereNull('processed_at')->first());
        $this->assertNotNull($ev, 'yenidən cəhd üçün növbədə qalır');
        $this->assertStringContainsString('clamd unreachable', $ev->last_error);
    }

    public function test_download_requires_signature_and_ownership(): void
    {
        [$c, $L, $a, $s] = $this->setupCourse();
        $this->signIn($s)->post("/platform/assignments/{$a->id}/submit", ['body' => 'x', 'files' => [$this->pdf()]]);
        Outbox::process();
        $f = UF::firstOrFail();
        $this->get("/platform/files/{$f->id}")->assertStatus(403);       // imzasız
        $link = FileController::link($f);
        $other = $this->makeUser();
        $this->enroll($other, $c);
        $this->signIn($other)->get($link)->assertNotFound();               // başqa tələbə (RLS: tapılmır)
        $this->signIn($this->instructorFor($c))->get($link)->assertOk();   // öz kursunun təlimçisi
        ['course' => $d] = $this->buildCourse('digital-marketinq-kursu', modules: 1, lessonsPer: 1);
        $this->signIn($this->instructorFor($d))->get($link)->assertNotFound(); // başqa kursun təlimçisi
        $this->signIn($s);
        $this->travel(6)->minutes();
        $this->get($link)->assertStatus(403);                              // imza vaxtı bitib
    }

    public function test_type_size_quota_and_count_limits(): void
    {
        [$c, $L, $a, $s] = $this->setupCourse();
        $this->signIn($s);
        $this->post("/platform/assignments/{$a->id}/submit", ['files' => [UploadedFile::fake()->createWithContent('x.pdf', 'MZ fake')]])
            ->assertSessionHasErrors('files');
        $this->post("/platform/assignments/{$a->id}/submit", ['files' => [UploadedFile::fake()->createWithContent('m.docm', 'x')]])->assertSessionHasErrors('files');
        System::run(fn () => DB::table('billing_rules')->whereNull('course_id')->update(['upload_quota_mb' => 1, 'upload_files_per_submission' => 2]));
        $this->post("/platform/assignments/{$a->id}/submit", ['files' => [$this->pdf('a.pdf', str_repeat('a', 700 * 1024))]])->assertSessionHas('status');
        $this->post("/platform/assignments/{$a->id}/submit", ['files' => [$this->pdf('b.pdf', str_repeat('b', 700 * 1024))]])
            ->assertSessionHasErrors('files');
        $this->assertSame(__('assess.file_rejected.quota'), session('errors')->first('files'));
        $this->post("/platform/assignments/{$a->id}/submit", ['files' => [$this->pdf('1.pdf'), $this->pdf('2.pdf'), $this->pdf('3.pdf')]])
            ->assertSessionHasErrors('files');
    }

    public function test_chunked_upload_then_submit_with_staged_file(): void
    {
        [$c, $L, $a, $s] = $this->setupCourse();
        $docx = file_get_contents(FileValidatorTest::ooxml('docx'));
        $this->signIn($s);
        $init = $this->postJson("/platform/api/assignments/{$a->id}/uploads", ['name' => 'esse.docx', 'size' => strlen($docx)])->assertCreated()->json();
        $half = intdiv(strlen($docx), 2);
        $this->call('PUT', "/platform/api/uploads/{$init['upload_id']}?offset=0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], substr($docx, 0, $half))->assertOk();
        $this->call('PUT', "/platform/api/uploads/{$init['upload_id']}?offset=0", [], [], [], [], 'x')->assertStatus(409); // səhv offset
        $this->call('PUT', "/platform/api/uploads/{$init['upload_id']}?offset={$half}", [], [], [], [], substr($docx, $half))->assertOk();
        $fid = $this->postJson("/platform/api/uploads/{$init['upload_id']}/complete")->assertCreated()->json('file_id');
        $this->post("/platform/assignments/{$a->id}/submit", ['body' => 'esse', 'staged' => [$fid]])->assertSessionHas('status');
        $this->assertNotNull(UF::find($fid)->submission_id);
        // başqasının yükləmə sessiyasına yazmaq olmur
        $init2 = $this->postJson("/platform/api/assignments/{$a->id}/uploads", ['name' => 'x.pdf', 'size' => 10])->json();
        $this->signIn($this->makeUser())->call('PUT', "/platform/api/uploads/{$init2['upload_id']}?offset=0", [], [], [], [], 'abc')->assertNotFound();
    }

    public function test_demo_and_unpaid_users_get_403_on_assignment_endpoints(): void
    {
        [$c, $L, $a] = $this->setupCourse();
        $demoUser = $this->makeUser(['phone_e164' => '+994501110000']);
        System::run(fn () => DemoClaim::create(['user_id' => $demoUser->id, 'course_id' => $c->id, 'email_normalized' => $demoUser->email,
            'phone_e164' => '+994501110000', 'started_at' => now()->startOfSecond(), 'expires_at' => now()->startOfSecond()->addHours(24)]));
        $this->signIn($demoUser);
        $this->postJson("/platform/api/lessons/{$L[1][1]->id}/playback")->assertOk(); // demo özü işləyir
        $this->getJson("/platform/api/assignments/{$a->id}")->assertStatus(403);
        $this->post("/platform/assignments/{$a->id}/submit", ['body' => 'x'])->assertStatus(403);
        $this->postJson("/platform/api/assignments/{$a->id}/uploads", ['name' => 'a.pdf', 'size' => 5])->assertStatus(403);
        $this->getJson("/platform/api/courses/{$c->slug}/scores")->assertStatus(403);
        $this->signIn($this->makeUser())->getJson("/platform/api/assignments/{$a->id}")->assertStatus(403);
    }

    public function test_student_cannot_see_other_students_submissions_or_grades_rls(): void
    {
        [$c, $L, $a, $s] = $this->setupCourse();
        $this->submitAs($s, $a, 'gizli cavab');
        $other = $this->makeUser();
        DbContext::actAs($other->id);
        $this->assertSame(0, DB::table('submissions')->count());
        $this->assertSame(0, DB::table('uploaded_files')->count());
        $this->signIn($other)->get('/platform/admin/grading/'.Submission::withoutGlobalScopes()->first()?->id ?? 1)->assertStatus(403);
    }
}
