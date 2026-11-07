<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo: bütün platformada bir dəfə (bölmə 1b, 2.2). Unikallıq VERİLƏNLƏR BAZASI səviyyəsindədir:
 * user_id, normallaşdırılmış e-mail və E.164 telefon. Paralel qeydiyyatda biri uğurlu, digəri rədd olunur.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_owner';

    public function up(): void
    {
        Schema::create('demo_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained();
            $t->string('email_normalized', 254)->unique();
            $t->string('phone_e164', 20)->unique();
            $t->timestampTz('started_at');
            $t->timestampTz('expires_at');                  // started_at + 24 saat; heç vaxt uzadılmır
            $t->timestampTz('email_confirmed_at')->nullable();
            $t->timestampTz('converted_at')->nullable();      // həmin kurs alınanda
            $t->timestampTz('converted_other_at')->nullable(); // digər kurs alınanda (analitika)
            $t->string('source', 20)->default('landing');     // landing | dashboard
            $t->string('ip_hash', 64)->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['course_id', 'started_at']);
        });
        DB::statement('ALTER TABLE demo_claims ADD CONSTRAINT demo_claims_24h_chk CHECK (expires_at = started_at + interval \'24 hours\')');
        Rls::enable('demo_claims', 'user_id = app.uid() OR app.is_admin() OR app.is_worker()',
            insertCheck: 'user_id = app.uid() OR app.is_worker()');
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_claims');
    }
};
