<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ev tapşırıqları, fayllar, qiymətlər, diplomlar (bölmə 2.5, 2.9, 3.4).
 * - Tapşırığın mətni revizyalarla (assignment_revisions); maksimum bal və məcburilik VERSİYA üzrədir (version_assignments).
 * - grades: hər qiymət verildiyi andakı max_points snapshot-ını saxlayır; köhnə qiymətlər silinmir (superseded_at).
 * - certificates: UNIQUE(user_id, course_id) — ikinci diplom yaranmır; snapshot dəyişməzdir.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_owner';

    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('current_revision_id')->nullable();
            $t->timestampsTz();
        });

        Schema::create('uploaded_files', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 12);                         // submission | case_file
            $t->string('storage_key', 120)->unique();          // random; orijinal ad yalnız metadata
            $t->string('original_name', 255);
            $t->string('mime_detected', 120);
            $t->unsignedBigInteger('size_bytes');
            $t->char('sha256', 64);
            $t->string('scan_status', 10)->default('pending'); // pending | clean | infected | error
            $t->string('scan_result', 200)->nullable();
            $t->timestampTz('scanned_at')->nullable();
            $t->timestampTz('quarantined_at')->nullable();
            $t->unsignedBigInteger('submission_id')->nullable();
            $t->timestampsTz();
            $t->index(['owner_user_id', 'course_id']);
        });
        DB::statement("ALTER TABLE uploaded_files ADD CONSTRAINT uploaded_files_scan_chk CHECK (scan_status IN ('pending','clean','infected','error'))");

        Schema::create('assignment_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision_no');
            $t->jsonb('title');
            $t->jsonb('body');
            $t->timestampTz('due_at')->nullable();
            $t->uuid('case_file_id')->nullable();
            $t->foreign('case_file_id')->references('id')->on('uploaded_files')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['assignment_id', 'revision_no']);
        });
        Schema::table('assignments', fn (Blueprint $t) => $t->foreign('current_revision_id')->references('id')->on('assignment_revisions')->nullOnDelete());

        Schema::create('version_assignments', function (Blueprint $t) {
            $t->foreignId('course_version_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('max_points');
            $t->boolean('is_mandatory')->default(true);
            $t->primary(['course_version_id', 'assignment_id']);
        });
        DB::statement('ALTER TABLE version_assignments ADD CONSTRAINT version_assignments_points_chk CHECK (max_points > 0)');

        Schema::create('submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->string('status', 16)->default('submitted');   // submitted | reviewing | graded | needs_revision
            $t->text('body_html')->nullable();                 // HTMLPurifier ilə sanitizasiya olunmuş
            $t->timestampTz('submitted_at');
            $t->timestampsTz();
            $t->unique(['user_id', 'assignment_id']);
            $t->index(['course_id', 'status']);
        });
        Schema::table('uploaded_files', fn (Blueprint $t) => $t->foreign('submission_id')->references('id')->on('submissions')->nullOnDelete());

        Schema::create('grades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('points');
            $t->unsignedInteger('max_points_snapshot');        // qiymət anındakı maksimum bal — sonradan dəyişmir
            $t->foreignId('course_version_id')->constrained();
            $t->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('feedback')->nullable();
            $t->timestampTz('superseded_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_points_chk CHECK (points <= max_points_snapshot)');
        DB::statement('CREATE UNIQUE INDEX grades_one_current ON grades (submission_id) WHERE superseded_at IS NULL');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app.grades_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
              IF NEW.points <> OLD.points OR NEW.max_points_snapshot <> OLD.max_points_snapshot OR NEW.submission_id <> OLD.submission_id THEN
                RAISE EXCEPTION 'grades are immutable: create a new grade row instead';
              END IF;
              RETURN NEW;
            END $$;
            CREATE TRIGGER grades_no_change BEFORE UPDATE ON grades FOR EACH ROW EXECUTE FUNCTION app.grades_immutable();
        SQL);

        Schema::create('certificates', function (Blueprint $t) {
            $t->id();
            $t->string('verify_id', 32)->unique();             // 128-bit random, base32 (təxmin olunmur)
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('course_id')->constrained();
            $t->jsonb('snapshot');                             // ad, kurs, təlimçi, bal, faiz, tarix
            $t->timestampTz('issued_at');
            $t->timestampTz('revoked_at')->nullable();
            $t->text('revoke_reason')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unique(['user_id', 'course_id']);
        });
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app.certificate_snapshot_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
              IF NEW.snapshot <> OLD.snapshot OR NEW.verify_id <> OLD.verify_id OR NEW.issued_at <> OLD.issued_at THEN
                RAISE EXCEPTION 'certificate snapshot is immutable';
              END IF;
              RETURN NEW;
            END $$;
            CREATE TRIGGER certificates_immutable BEFORE UPDATE ON certificates FOR EACH ROW EXECUTE FUNCTION app.certificate_snapshot_immutable();
        SQL);
        // Açıq yoxlama qeydi: yalnız ad, kurs, tarix (bilərəkdən açıq məlumat, RLS yoxdur).
        // Hesab silinəndə də qalır (bölmə 2.14); e-mail/telefon burada heç vaxt saxlanılmır.
        Schema::create('certificate_verifications', function (Blueprint $t) {
            $t->string('verify_id', 32)->primary();
            $t->string('holder', 120);
            $t->jsonb('course');
            $t->timestampTz('issued_at');
            $t->timestampTz('revoked_at')->nullable();
        });

        $own = 'user_id = app.uid() OR app.is_admin() OR app.teaches(course_id) OR app.is_worker()';
        Rls::enable('submissions', $own, insertCheck: 'user_id = app.uid() OR app.is_worker()');
        Rls::enable('grades', $own, insertCheck: 'app.is_admin() OR app.teaches(course_id) OR app.is_worker()');
        Rls::enable('certificates', $own, insertCheck: 'user_id = app.uid() OR app.is_admin() OR app.teaches(course_id) OR app.is_worker()');
        Rls::enable('uploaded_files',
            "owner_user_id = app.uid() OR app.is_admin() OR app.teaches(course_id) OR app.is_worker()
             OR (purpose = 'case_file' AND EXISTS (SELECT 1 FROM enrollments e WHERE e.user_id = app.uid() AND e.course_id = uploaded_files.course_id))",
            insertCheck: 'owner_user_id = app.uid() OR app.is_worker()');
    }

    public function down(): void
    {
        foreach (['certificate_verifications', 'certificates', 'grades', 'submissions', 'version_assignments', 'assignment_revisions', 'uploaded_files', 'assignments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
