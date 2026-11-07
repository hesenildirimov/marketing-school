<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Məzmun modeli + versiya manifesti (bölmə 3.3, 3.4) + enrollment və billing qaydaları.
 * Modul/dərs/tapşırıq daimi obyektlərdir (progress lesson_id-yə bağlıdır, itmir);
 * kurs versiyası hansı modul və dərslərin daxil olduğunu göstərən manifestdir.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_owner';

    public function up(): void
    {
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('external_id', 80)->unique();       // import açarı (landing JSON slug-u)
            $t->jsonb('name');                               // {az,en,ru}
            $t->unsignedSmallInteger('demo_module_count')->default(2);
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->timestampsTz();
        });

        Schema::create('course_instructors', function (Blueprint $t) {
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['course_id', 'user_id']);
        });
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app.teaches(cid bigint) RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS
              $$ SELECT EXISTS (SELECT 1 FROM course_instructors ci JOIN user_roles r ON r.user_id = ci.user_id AND r.role = 'instructor'
                                WHERE ci.user_id = app.uid() AND ci.course_id = cid) $$;
        SQL);

        Schema::create('course_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version_no');
            $t->string('status', 12)->default('draft');      // draft | published
            $t->timestampTz('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('apply_mode', 12)->nullable();        // new_only | all
            $t->text('change_reason')->nullable();
            $t->timestampsTz();
            $t->unique(['course_id', 'version_no']);
        });
        DB::statement("ALTER TABLE course_versions ADD CONSTRAINT course_versions_status_chk CHECK (status IN ('draft','published'))");
        DB::statement('CREATE UNIQUE INDEX course_versions_one_draft ON course_versions (course_id) WHERE status = \'draft\'');
        Schema::table('courses', fn (Blueprint $t) => $t->foreign('current_version_id')->references('id')->on('course_versions')->nullOnDelete());

        Schema::create('modules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('external_key', 80);
            $t->jsonb('title');
            $t->jsonb('description')->nullable();
            $t->timestampsTz();
            $t->unique(['course_id', 'external_key']);
        });

        Schema::create('version_modules', function (Blueprint $t) {
            $t->foreignId('course_version_id')->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->boolean('demo_open')->default(false);
            $t->primary(['course_version_id', 'module_id']);
        });
        DB::statement('ALTER TABLE version_modules ADD CONSTRAINT version_modules_pos_uq UNIQUE (course_version_id, position) DEFERRABLE INITIALLY DEFERRED');

        Schema::create('video_assets', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20);
            $t->string('provider_video_id', 100);
            $t->string('title', 200)->nullable();
            $t->string('status', 12)->default('uploading'); // uploading|processing|ready|failed
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->timestampsTz();
            $t->unique(['provider', 'provider_video_id']);
        });

        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->constrained()->cascadeOnDelete();
            $t->string('external_key', 80)->nullable();
            $t->unsignedBigInteger('current_revision_id')->nullable();
            $t->timestampsTz();
            $t->unique(['module_id', 'external_key']);
        });

        Schema::create('lesson_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision_no');
            $t->jsonb('title');
            $t->jsonb('body')->nullable();
            $t->foreignId('video_asset_id')->nullable()->constrained()->nullOnDelete();
            $t->jsonb('chapters')->nullable();               // [{t: saniyə, title: {az,en,ru}}]
            $t->jsonb('materials')->nullable();              // [{title, url}] (fayllar M4-də uploaded_files ilə)
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('change_note')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['lesson_id', 'revision_no']);
        });
        Schema::table('lessons', fn (Blueprint $t) => $t->foreign('current_revision_id')->references('id')->on('lesson_revisions')->nullOnDelete());

        Schema::create('version_lessons', function (Blueprint $t) {
            $t->foreignId('course_version_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->primary(['course_version_id', 'lesson_id']);
            $t->index(['course_version_id', 'module_id', 'position']);
        });

        // Billing qaydaları konfiqurasiyadır (bölmə 1b, 1c): koda sərt yazılmır, admin dəyişir, audit log-a düşür.
        Schema::create('billing_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->nullable()->unique()->constrained()->cascadeOnDelete(); // NULL = qlobal
            $t->unsignedSmallInteger('grace_period_days')->default(3);
            $t->unsignedSmallInteger('min_commitment_months')->nullable();   // 1c-1: default yoxdur
            $t->boolean('auto_stop_on_completion')->default(false);          // 1c-3: default yalnız tələbə/admin
            $t->string('refund_access_rule', 30)->default('revoke_covered_period'); // 1c-6
            $t->string('grace_payment_rule', 30)->default('extend_from_paid_until'); // 1c-7
            $t->unsignedSmallInteger('session_limit')->default(2);
            $t->unsignedSmallInteger('mentoring_cancel_hours')->default(24);  // 1c-13
            $t->unsignedInteger('upload_file_mb')->default(20);              // 1c-16
            $t->unsignedInteger('upload_quota_mb')->default(200);
            $t->unsignedSmallInteger('upload_files_per_submission')->default(5);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX billing_rules_one_global ON billing_rules ((course_id IS NULL)) WHERE course_id IS NULL');
        DB::table('billing_rules')->insert(['course_id' => null, 'created_at' => now(), 'updated_at' => now()]);

        // Enrollment: kurs üzrə giriş. Akademik məlumat (progress, ballar, diplom) ayrı cədvəllərdədir və silinmir.
        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_version_id')->constrained();
            $t->timestampTz('paid_until')->nullable();           // access_periods-dan törənir (M6)
            $t->timestampTz('canceled_at')->nullable();
            $t->string('cancel_reason', 200)->nullable();
            $t->string('status_cached', 12)->nullable();
            $t->timestampTz('modules_completed_at')->nullable();
            $t->timestampTz('mentoring_unlocked_at')->nullable(); // bir dəfə yazılır, sonradan silinmir
            $t->timestampTz('diploma_eligible_at')->nullable();
            $t->timestampsTz();
            $t->unique(['user_id', 'course_id']);                 // bir istifadəçi + kurs = bir enrollment
        });

        Schema::create('lesson_progress', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position_seconds')->default(0);
            $t->unsignedInteger('max_watched_seconds')->default(0);
            $t->decimal('watch_percent', 5, 2)->default(0);
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();
            $t->primary(['user_id', 'lesson_id']);
            $t->index(['course_id', 'lesson_id']);
        });

        Schema::create('personal_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('at_second')->nullable();
            $t->text('body');
            $t->timestampsTz();
            $t->index(['user_id', 'lesson_id']);
        });

        Schema::create('video_token_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->string('ip_hash', 64)->nullable();
            $t->string('kind', 10)->default('issue');           // issue | refresh | denied
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });

        // Player metrikləri: şəxsi məlumat YOXDUR (user_id saxlanılmır).
        Schema::create('player_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('ttff_ms')->nullable();
            $t->unsignedSmallInteger('rebuffer_count')->default(0);
            $t->unsignedInteger('rebuffer_ms')->default(0);
            $t->unsignedInteger('avg_bitrate_kbps')->nullable();
            $t->unsignedSmallInteger('quality_switches')->default(0);
            $t->string('device', 10)->nullable();               // mobile | desktop
            $t->timestampTz('created_at')->useCurrent();
        });

        $own = 'user_id = app.uid() OR app.is_admin() OR app.teaches(course_id) OR app.is_worker()';
        Rls::enable('enrollments', $own, insertCheck: 'user_id = app.uid() OR app.is_admin() OR app.is_worker()');
        Rls::enable('lesson_progress', $own, insertCheck: 'user_id = app.uid() OR app.is_worker()');
        // Şəxsi qeydlər: təlimçi də görmür
        Rls::enable('personal_notes', 'user_id = app.uid() OR app.is_worker()');
        Rls::enable('video_token_log', 'user_id = app.uid() OR app.is_admin() OR app.is_worker()');
    }

    public function down(): void
    {
        foreach (['player_metrics', 'video_token_log', 'personal_notes', 'lesson_progress', 'enrollments', 'billing_rules', 'version_lessons',
            'lesson_revisions', 'lessons', 'video_assets', 'version_modules', 'modules', 'course_versions', 'course_instructors', 'courses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
