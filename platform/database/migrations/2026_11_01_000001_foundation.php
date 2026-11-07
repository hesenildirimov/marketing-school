<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql_owner';

    public function up(): void
    {
        $app = Rls::appRole();
        $worker = Rls::workerRole();

        // Yeni cədvəllər avtomatik olaraq yalnız DML icazəsi alır (DDL yox).
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$app}, {$worker}");
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$app}, {$worker}");

        // RLS köməkçi funksiyaları. İstifadəçi ID-si sorğunun əvvəlində middleware tərəfindən
        // set_config('app.user_id') ilə yazılır. Rol (admin/təlimçi) app-dən ötürülmür, cədvəldən oxunur.
        DB::unprepared(<<<'SQL'
            CREATE SCHEMA IF NOT EXISTS app;
            CREATE OR REPLACE FUNCTION app.uid() RETURNS bigint LANGUAGE sql STABLE AS
              $$ SELECT NULLIF(current_setting('app.user_id', true), '')::bigint $$;
        SQL);
        DB::statement("GRANT USAGE ON SCHEMA app TO {$app}, {$worker}");

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email', 254)->unique();            // kiçik hərflə saxlanılır
            $t->string('email_normalized', 254)->index();   // +tag, gmail nöqtələri silinmiş
            $t->string('phone_e164', 20)->nullable()->index();
            $t->string('password')->nullable();             // NULL: parol hələ təyin olunmayıb
            $t->string('locale', 2)->default('az');
            $t->char('short_code', 8)->unique();             // watermark üçün qısa kod
            $t->timestampTz('email_verified_at')->nullable();
            $t->timestampTz('consent_at')->nullable();
            $t->timestampTz('deleted_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create('user_roles', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20);
            $t->timestampTz('created_at')->useCurrent();
            $t->primary(['user_id', 'role']);
        });
        DB::statement("ALTER TABLE user_roles ADD CONSTRAINT user_roles_role_chk CHECK (role IN ('student','instructor','admin'))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app.is_admin() RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS
              $$ SELECT EXISTS (SELECT 1 FROM user_roles WHERE user_id = app.uid() AND role = 'admin') $$;
        SQL);
        DB::unprepared(sprintf(<<<'SQL'
            CREATE OR REPLACE FUNCTION app.is_worker() RETURNS boolean LANGUAGE sql STABLE AS
              $$ SELECT current_user = '%s' $$;
        SQL, $worker));

        // Server tərəfində saxlanılan və ləğv oluna bilən sessiyalar (cihaz limiti üçün).
        Schema::create('user_sessions', function (Blueprint $t) {
            $t->char('id', 64)->primary();                   // sha256(random token)
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('ip_hash', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('last_seen_at')->useCurrent();
            $t->timestampTz('two_factor_at')->nullable();
            $t->timestampTz('reauth_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->string('revoke_reason', 40)->nullable();
            $t->index(['user_id', 'revoked_at']);
        });

        // Birdəfəlik e-mail tokenləri: DB-də yalnız sha256 hash.
        Schema::create('auth_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 20);
            $t->char('token_hash', 64)->unique();
            $t->timestampTz('expires_at');
            $t->timestampTz('used_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement('CREATE UNIQUE INDEX auth_tokens_one_active ON auth_tokens (user_id, purpose) WHERE used_at IS NULL AND revoked_at IS NULL');

        Schema::create('two_factor_secrets', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $t->text('secret_encrypted');
            $t->timestampTz('confirmed_at')->nullable();
            $t->bigInteger('last_used_step')->nullable();    // eyni TOTP kodunun təkrar istifadəsini bloklayır
            $t->timestampsTz();
        });

        Schema::create('recovery_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->char('code_hash', 64)->unique();
            $t->timestampTz('used_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });

        // Laravel-in daxili cədvəlləri (sessiya payload-ı, cache, rate limit)
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->bigInteger('expiration')->index();
        });
        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->bigInteger('expiration')->index();
        });

        // Dəyişməz audit log: hash zənciri + UPDATE/DELETE trigger ilə bloklanır.
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 80);
            $t->string('entity_type', 60)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->jsonb('before')->nullable();
            $t->jsonb('after')->nullable();
            $t->text('reason')->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->char('prev_hash', 64)->nullable();
            $t->char('hash', 64);
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['entity_type', 'entity_id']);
            $t->index('action');
        });
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION app.audit_immutable() RETURNS trigger LANGUAGE plpgsql AS
              $$ BEGIN RAISE EXCEPTION 'audit_logs is append-only'; END $$;
            CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OR DELETE ON audit_logs
              FOR EACH ROW EXECUTE FUNCTION app.audit_immutable();
        SQL);
        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM {$app}, {$worker}");

        // Transactional outbox: biznes tranzaksiyası ilə eyni tranzaksiyada yazılır.
        Schema::create('outbox', function (Blueprint $t) {
            $t->id();
            $t->string('event_type', 80);
            $t->jsonb('payload');
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('available_at')->useCurrent();
            $t->timestampTz('processed_at')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
        });
        DB::statement('CREATE INDEX outbox_pending ON outbox (available_at) WHERE processed_at IS NULL');

        // Vahid bildiriş modulu: e-mail və platforma daxili bildirişlər (ayrı kanallar).
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('channel', 10);                  // email | in_app
            $t->string('event_type', 80);
            $t->string('entity_type', 60)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->timestampTz('scheduled_for');
            $t->char('dedupe_key', 64)->unique();       // sha256(event + entity + user + scheduled_for + channel)
            $t->string('locale', 2);
            $t->unsignedSmallInteger('priority')->default(5); // 0 = ən yüksək (demo linki)
            $t->jsonb('payload')->nullable();
            $t->string('status', 12)->default('pending'); // pending|sent|failed|canceled
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestampTz('next_attempt_at')->nullable();
            $t->timestampTz('sent_at')->nullable();
            $t->timestampTz('read_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestampsTz();
            $t->index(['user_id', 'channel', 'read_at']);
        });
        DB::statement("CREATE INDEX notifications_due ON notifications (priority, next_attempt_at) WHERE status = 'pending'");

        Schema::create('disposable_email_domains', function (Blueprint $t) {
            $t->string('domain', 190)->primary();
            $t->string('source', 40)->default('seed');
            $t->timestampTz('created_at')->useCurrent();
        });

        // RLS: istifadəçi yalnız öz sessiyalarını və bildirişlərini görür.
        Rls::enable('user_sessions', 'user_id = app.uid() OR app.is_admin() OR app.is_worker()');
        Rls::enable('notifications', 'user_id = app.uid() OR app.is_admin() OR app.is_worker()', insertCheck: 'true');
    }

    public function down(): void
    {
        foreach (['disposable_email_domains', 'notifications', 'outbox', 'audit_logs', 'cache_locks', 'cache', 'sessions',
            'recovery_codes', 'two_factor_secrets', 'auth_tokens', 'user_sessions', 'user_roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP SCHEMA IF EXISTS app CASCADE');
    }
};
