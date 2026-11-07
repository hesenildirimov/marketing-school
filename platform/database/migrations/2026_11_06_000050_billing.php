<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (bölmə 1b, 2.10). Ödəniş aktivliyi akademik məlumatdan ayrıdır.
 * - prices: məbləğ qəpiklə tam ədəd; köhnə sifarişlər dəyişmir (sifariş öz məbləğini saxlayır).
 * - access_periods: ödənilmiş dövrlər; paid_until = ləğv edilməmiş dövrlərin max(ends_at). Refund dövrü ləğv edir.
 * - payment_webhook_events: UNIQUE(provider, provider_event_id) — təkrar gələn webhook ikinci dəfə tətbiq olunmur.
 * - payment_transactions: UNIQUE(provider_txn_id); access_periods.transaction_id UNIQUE — bir ödəniş bir dəfə.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_owner';

    public function up(): void
    {
        Schema::create('prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('amount_qepik');
            $t->char('currency', 3)->default('AZN');
            $t->timestampTz('valid_from');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['course_id', 'valid_from']);
        });
        DB::statement('ALTER TABLE prices ADD CONSTRAINT prices_amount_chk CHECK (amount_qepik > 0)');

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 20);
            $t->text('provider_ref_encrypted')->nullable();  // kart tokeni (card_id) — kart nömrəsi YOX
            $t->string('card_last4', 4)->nullable();
            $t->string('status', 12)->default('active');     // active | past_due | canceled
            $t->boolean('cancel_at_period_end')->default(false);
            $t->timestampTz('next_charge_at')->nullable();
            $t->unsignedSmallInteger('failed_attempts')->default(0);
            $t->timestampTz('canceled_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX subscriptions_one_open ON subscriptions (enrollment_id) WHERE status <> 'canceled'");

        Schema::create('payment_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('price_id')->constrained();
            $t->unsignedInteger('amount_qepik');               // serverdə prices-dan, müştəridən YOX
            $t->char('currency', 3);
            $t->string('purpose', 10)->default('initial');     // initial | renewal | manual
            $t->string('status', 14)->default('created');      // created|pending|failed|paid|refunded|charged_back
            $t->string('provider', 20);
            $t->string('provider_order_id', 100)->nullable()->unique();
            $t->boolean('save_card')->default(true);
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->timestampTz('paid_at')->nullable();
            $t->timestampsTz();
            $t->index(['status', 'created_at']);
        });

        // Webhook istifadəçi konteksti olmadan gəlir: sifarişin sahibini tapmaq üçün minimal açıq xəritə
        // (yalnız UUID → user_id; həssas məlumat yoxdur). Sonra emal sahibin RLS konteksti ilə aparılır.
        Schema::create('payment_order_owners', function (Blueprint $t) {
            $t->uuid('order_id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('payment_transactions', function (Blueprint $t) {
            $t->id();
            $t->uuid('order_id');
            $t->foreign('order_id')->references('id')->on('payment_orders')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('provider_txn_id', 120);
            $t->string('kind', 12);                            // charge | refund | chargeback
            $t->integer('amount_qepik');
            $t->string('raw_status', 40)->nullable();
            $t->timestampTz('occurred_at');
            $t->timestampTz('applied_at')->useCurrent();
            $t->unique(['provider_txn_id', 'kind']);
        });

        Schema::create('access_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('source', 12);                          // payment | manual | adjustment
            $t->foreignId('transaction_id')->nullable()->unique()->constrained('payment_transactions')->nullOnDelete();
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->timestampTz('revoked_at')->nullable();
            $t->string('revoke_reason', 40)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE access_periods ADD CONSTRAINT access_periods_range_chk CHECK (ends_at > starts_at)');

        Schema::create('payment_webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20);
            $t->string('provider_event_id', 200);
            $t->boolean('signature_valid');
            $t->jsonb('payload_redacted');
            $t->string('status', 12)->default('received');     // received | processed | failed | ignored
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
            $t->timestampTz('received_at')->useCurrent();
            $t->timestampTz('processed_at')->nullable();
            $t->unique(['provider', 'provider_event_id']);
        });

        $own = 'user_id = app.uid() OR app.is_admin() OR app.is_worker()';
        Rls::enable('payment_orders', $own);
        Rls::enable('payment_transactions', $own);
        Rls::enable('subscriptions', $own);
        Rls::enable('access_periods', $own.' OR app.teaches(course_id)', insertCheck: $own);
    }

    public function down(): void
    {
        foreach (['payment_order_owners', 'payment_webhook_events', 'access_periods', 'payment_transactions', 'payment_orders', 'subscriptions', 'prices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
