<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canlı sual-cavab (bölmə 2.6) və 1:1 mentorluq (bölmə 2.7).
 * - live_sessions: starts_at UTC; 2-ci/4-cü şənbə Asia/Baku-da hesablanır; UNIQUE(course_id, starts_at) və
 *   UNIQUE(course_id, occurrence_key) — admin görüşü köçürsə belə, generator həmin tarixi yenidən yaratmır.
 * - mentor_slots: təlimçinin slotları üst-üstə düşə bilməz (EXCLUDE gist).
 * - mentor_bookings: bir slot = bir aktiv rezerv; bir enrollment = bir hüquq (partial UNIQUE).
 */
return new class extends Migration
{
    protected $connection = 'pgsql_owner';

    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->date('occurrence_key')->nullable();             // avtomatik seriyanın orijinal tarixi (Bakı)
            $t->timestampTz('starts_at');
            $t->unsignedSmallInteger('duration_minutes')->default(60);
            $t->string('status', 10)->default('scheduled');    // scheduled | canceled
            $t->text('meeting_url_encrypted')->nullable();      // Zoom/Meet linki (APP_KEY ilə şifrələnmiş)
            $t->foreignId('recording_video_asset_id')->nullable()->constrained('video_assets')->nullOnDelete();
            $t->uuid('ics_uid')->unique();
            $t->unsignedInteger('ics_sequence')->default(0);
            $t->string('source', 10)->default('auto');
            $t->timestampsTz();
            $t->unique(['course_id', 'starts_at']);
            $t->unique(['course_id', 'occurrence_key']);
        });

        Schema::create('live_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestampTz('created_at')->useCurrent();
        });

        Schema::create('live_session_assignments', function (Blueprint $t) {
            $t->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->primary(['live_session_id', 'assignment_id']);
        });

        Schema::create('mentor_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->text('meeting_url_encrypted')->nullable();
            $t->boolean('is_booked')->default(false);         // göstəriş üçün (tələbə başqasının rezervini RLS səbəbindən görmür)
            $t->timestampTz('deleted_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE mentor_slots ADD CONSTRAINT mentor_slots_range_chk CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE mentor_slots ADD CONSTRAINT mentor_slots_no_overlap EXCLUDE USING gist
            (instructor_id WITH =, tstzrange(starts_at, ends_at) WITH &&) WHERE (deleted_at IS NULL)');

        Schema::create('mentor_bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('slot_id')->constrained('mentor_slots')->cascadeOnDelete();
            $t->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('status', 12)->default('booked');       // booked | canceled | completed | no_show
            $t->timestampTz('booked_at');
            $t->timestampTz('canceled_at')->nullable();
            $t->string('cancel_reason', 200)->nullable();
            $t->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX mentor_bookings_slot_once ON mentor_bookings (slot_id) WHERE status = 'booked'");
        DB::statement("CREATE UNIQUE INDEX mentor_bookings_one_right ON mentor_bookings (enrollment_id) WHERE status IN ('booked','completed','no_show')");

        $own = 'user_id = app.uid() OR app.is_admin() OR app.teaches(course_id) OR app.is_worker()';
        Rls::enable('live_questions', $own, insertCheck: 'user_id = app.uid() OR app.is_worker()');
        Rls::enable('mentor_bookings', $own, insertCheck: 'user_id = app.uid() OR app.is_admin() OR app.is_worker()');
    }

    public function down(): void
    {
        foreach (['mentor_bookings', 'mentor_slots', 'live_session_assignments', 'live_questions', 'live_sessions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
