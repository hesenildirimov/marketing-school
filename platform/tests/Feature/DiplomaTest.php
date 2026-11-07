<?php

namespace Tests\Feature;

use App\Domain\Assessment\AssignmentService;
use App\Domain\Assessment\GradingService;
use App\Domain\Certificates\DiplomaService;
use App\Domain\Content\VersionService;
use App\Models\AppNotification;
use App\Models\Certificate;
use App\Models\Grade;
use App\Models\Submission;
use App\Support\System;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAssignments;
use Tests\Concerns\BuildsCourses;
use Tests\Support\Parallel;
use Tests\TestCase;

/** Bölmə 7 — Diplom testləri. */
class DiplomaTest extends TestCase
{
    use BuildsAssignments, BuildsCourses;

    private function setupWith(array $specs): array
    {
        ['course' => $c] = $this->buildCourse(modules: 1, lessonsPer: 1);
        $as = $this->addAssignments($c, $specs);
        $s = $this->makeUser(['name' => 'Leyla Həsənova']);
        $e = $this->enroll($s, $c);
        $t = $this->instructorFor($c);

        return [$c, $as, $s, $e, $t];
    }

    private function score($e): array
    {
        return System::run(fn () => app(DiplomaService::class)->score($e));
    }

    private function grade($t, $s, $a, int $points)
    {
        return app(GradingService::class)->grade($this->submitAs($s, $a), $points, 'yaxşı', 'graded', $t);
    }

    public function test_749_of_1000_no_diploma_750_diploma(): void
    {
        [$c, [$a1, $a2], $s, $e, $t] = $this->setupWith([[500, true], [500, true]]);
        $this->grade($t, $s, $a1, 500);
        $this->grade($t, $s, $a2, 249);
        $this->assertSame(749, $this->score($e->fresh())['earned']);
        $this->assertSame(0, Certificate::count(), '74,9% diplom vermir');
        $this->grade($t, $s, $a2, 250);
        $this->assertSame(1, System::run(fn () => Certificate::count()), '75% diplom verir');
        $cert = System::run(fn () => Certificate::first());
        $this->assertSame('75.0', $cert->snapshot['percent']);
        $this->assertSame('Leyla Həsənova', $cert->snapshot['name']);
    }

    public function test_optional_assignments_are_not_in_denominator_and_unsubmitted_count_as_zero(): void
    {
        [$c, [$m1, $m2, $opt], $s, $e] = $this->setupWith([[10, true], [10, true], [100, false]]);
        $t = $this->instructorFor($c);
        $this->grade($t, $s, $m1, 10);   // m2 göndərilməyib → 0
        $sc = $this->score($e->fresh());
        $this->assertSame(['earned' => 10, 'possible' => 20], ['earned' => $sc['earned'], 'possible' => $sc['possible']]);
        $this->assertFalse($sc['eligible']);
        $this->grade($t, $s, $opt, 100);
        $this->assertFalse($this->score($e->fresh())['eligible'], 'könüllü tapşırıq məxrəcə və suraya daxil deyil');
    }

    public function test_zero_possible_points_never_issues(): void
    {
        [$c, [$opt], $s, $e, $t] = $this->setupWith([[10, false]]);
        $this->grade($t, $s, $opt, 10);
        $sc = $this->score($e->fresh());
        $this->assertSame(0, $sc['possible']);
        $this->assertFalse($sc['eligible']);
        $this->assertSame(0, System::run(fn () => Certificate::count()));
    }

    public function test_diploma_survives_payment_expiry_and_redownloads(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 8);
        $cert = System::run(fn () => Certificate::firstOrFail());
        System::run(fn () => $e->update(['paid_until' => now()->subMonths(2)]));
        $this->signIn($s)->get("/platform/courses/{$c->slug}")->assertStatus(403); // kursa giriş yoxdur
        $r = $this->get(route('certificates.pdf', $cert))->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->get('/platform/dashboard')->assertSee(__('assess.diploma_download'));
    }

    public function test_adding_mandatory_assignment_later_does_not_revoke_issued_diploma(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 10);
        $before = System::run(fn () => Certificate::firstOrFail());
        $this->addAssignments($c, [[100, true]], 'all');
        $impact = app(VersionService::class); // versiya dərc olundu, enrollment yeni versiyada
        $this->assertSame(10, $this->score($e->fresh())['earned']);
        $this->assertFalse($this->score($e->fresh())['eligible']);
        System::run(fn () => app(DiplomaService::class)->evaluate($s, $c));
        $after = System::run(fn () => Certificate::firstOrFail());
        $this->assertNull($after->revoked_at);
        $this->assertSame($before->snapshot, $after->snapshot);
    }

    public function test_no_second_diploma_even_in_parallel(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $sub = $this->submitAs($s, $a);
        System::run(fn () => Grade::create(['submission_id' => $sub->id, 'user_id' => $s->id, 'course_id' => $c->id, 'assignment_id' => $a->id,
            'points' => 10, 'max_points_snapshot' => 10, 'course_version_id' => $e->fresh()->course_version_id, 'created_at' => now()]));
        $res = Parallel::run(4, fn () => System::run(fn () => app(DiplomaService::class)->evaluate($s, $c)?->id));
        $this->assertCount(1, array_unique($res), json_encode($res));
        $this->assertSame(1, System::run(fn () => Certificate::count()));
    }

    public function test_snapshot_immutable_in_database_and_name_change_does_not_alter_diploma(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 9);
        $s->update(['name' => 'Yeni Ad']);
        $cert = System::run(fn () => Certificate::firstOrFail());
        $this->assertSame('Leyla Həsənova', $cert->snapshot['name']);
        $this->expectException(QueryException::class);
        System::run(fn () => DB::table('certificates')->update(['snapshot' => json_encode(['name' => 'Saxta'])]));
    }

    public function test_grade_keeps_max_points_snapshot_and_change_is_audited(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 6);
        $this->grade($t, $s, $a, 7);
        $grades = System::run(fn () => Grade::orderBy('id')->get());
        $this->assertCount(2, $grades);
        $this->assertNotNull($grades[0]->superseded_at);
        $this->assertSame(10, $grades[1]->max_points_snapshot);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'grade.changed')->count());
        // Qiymət sətri sonradan dəyişdirilə bilməz (DB trigger)
        $this->expectException(QueryException::class);
        System::run(fn () => DB::table('grades')->where('id', $grades[1]->id)->update(['points' => 10]));
    }

    public function test_max_points_change_after_grading_requires_admin_and_reason_and_keeps_old_grade(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 8);
        $draft = app(VersionService::class)->draft($c->fresh());
        try {
            app(AssignmentService::class)->setScoring($draft, $a, 20, true, $t, 'səbəb');
            $this->fail('təlimçi dəyişə bilməməlidir');
        } catch (ValidationException) {
        }
        $admin = $this->makeUser([], ['admin']);
        try {
            app(AssignmentService::class)->setScoring($draft, $a, 20, true, $admin, '');
            $this->fail('səbəbsiz olmaz');
        } catch (ValidationException) {
        }
        app(AssignmentService::class)->setScoring($draft, $a, 20, true, $admin, 'Keys genişləndirildi');
        app(VersionService::class)->publish($draft->fresh(), 'all', 'bal dəyişikliyi', $admin);
        $g = System::run(fn () => Grade::whereNull('superseded_at')->first());
        $this->assertSame(8, $g->points);
        $this->assertSame(10, $g->max_points_snapshot, 'verilmiş qiymət xəbərsiz dəyişmir');
        $this->assertSame(20, $this->score($e->fresh())['possible']);
    }

    public function test_public_verification_shows_only_name_course_date_and_is_rate_limited(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 10);
        $cert = System::run(fn () => Certificate::firstOrFail());
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{26}$/', $cert->verify_id);
        auth()->logout();
        $r = $this->get("/platform/diplom/{$cert->verify_id}")->assertOk()->assertSee('Leyla Həsənova')->assertSee(__('assess.verify_valid'))
            ->assertSee('linkedin.com/profile/add', false);
        $r->assertDontSee($s->email)->assertDontSee('8/10')->assertDontSee($cert->snapshot['percent'].'%');
        $this->get('/platform/diplom/AAAAAAAAAAAAAAAAAAAAAAAAAA')->assertNotFound();
        $this->get('/platform/diplom/../etc')->assertNotFound();
        for ($i = 0; $i < 18; $i++) {
            $this->get('/platform/diplom/BBBBBBBBBBBBBBBBBBBBBBBBB'.$i % 2);
        }
        $this->get('/platform/diplom/CCCCCCCCCCCCCCCCCCCCCCCCCC')->assertStatus(429);
    }

    public function test_revocation_requires_admin_reason_and_is_audited(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 10);
        $cert = System::run(fn () => Certificate::firstOrFail());
        $this->signIn($t)->post("/platform/admin/certificates/{$cert->id}/revoke", ['reason' => 'test səbəb'])->assertStatus(403);
        $admin = $this->makeUser([], ['admin']);
        $this->signIn($admin)->post("/platform/admin/certificates/{$cert->id}/revoke", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/platform/admin/certificates/{$cert->id}/revoke", ['reason' => 'Plagiat aşkarlandı'])->assertRedirect();
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'certificate.revoked')->count());
        $this->get("/platform/diplom/{$cert->verify_id}")->assertSee(__('assess.verify_revoked'));
        $this->signIn($s)->get(route('certificates.pdf', $cert))->assertStatus(410);
    }

    public function test_other_student_cannot_download_someone_elses_diploma(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->grade($t, $s, $a, 10);
        $cert = System::run(fn () => Certificate::firstOrFail());
        $this->signIn($this->makeUser())->get(route('certificates.pdf', $cert))->assertNotFound(); // RLS: görünmür
    }

    public function test_grading_flow_via_admin_ui_notifies_student(): void
    {
        [$c, [$a], $s, $e, $t] = $this->setupWith([[10, true]]);
        $this->signIn($s)->post("/platform/assignments/{$a->id}/submit", ['body' => 'Cavabım'])->assertSessionHas('status');
        $sub = Submission::firstOrFail();
        $this->signIn($t)->get('/platform/admin/grading')->assertOk()->assertSee($s->email);
        $this->get("/platform/admin/grading/{$sub->id}")->assertOk()->assertSee('Cavabım');
        $this->post("/platform/admin/grading/{$sub->id}", ['points' => 11, 'status' => 'graded'])->assertSessionHasErrors('points');
        $this->post("/platform/admin/grading/{$sub->id}", ['points' => 9, 'feedback' => '<b>Əla</b>', 'status' => 'graded'])->assertRedirect();
        $this->assertSame(1, System::run(fn () => AppNotification::where('user_id', $s->id)->where('event_type', 'assignment_graded')->where('channel', 'email')->count()));
        $this->signIn($s)->getJson("/platform/api/assignments/{$a->id}")->assertOk()->assertJson(['status' => 'graded', 'grade' => ['points' => 9, 'max' => 10]]);
    }
}
