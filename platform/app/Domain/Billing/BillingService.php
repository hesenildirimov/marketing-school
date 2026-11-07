<?php

namespace App\Domain\Billing;

use App\Domain\Access\AccessPolicy;
use App\Domain\Audit\Audit;
use App\Domain\Demo\DemoService;
use App\Domain\Learning\ProgressService;
use App\Domain\Notifications\Notifier;
use App\Models\AccessPeriod;
use App\Models\BillingRule;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentOrder;
use App\Models\PaymentTransaction;
use App\Models\Price;
use App\Models\Subscription;
use App\Models\User;
use App\Support\DbContext;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Billing (bölmə 1b, 2.10). Əsas qaydalar:
 * - məbləğ serverdə prices cədvəlindən; enrollment yalnız TƏSDİQLƏNMİŞ ödənişdən sonra (fetchStatus);
 * - ödəniş qeydi + tranzaksiya + access_period + enrollment + subscription BİR tranzaksiyada, sifariş FOR UPDATE;
 * - status maşını yalnız irəli; təkrar/paralel webhook bir dəfə tətbiq olunur (UNIQUE txn, UNIQUE access_periods.transaction_id);
 * - billing statusu (subscription) enrollment giriş statusundan ayrıdır.
 */
final class BillingService
{
    public function __construct(private PaymentProvider $provider, private AccessPolicy $access) {}

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    public function checkout(User $user, Course $course): string
    {
        $price = Price::current($course->id) ?? abort(422, 'no price');
        $order = DbContext::as($user->id, function () use ($user, $course, $price) {
            $o = PaymentOrder::create(['user_id' => $user->id, 'course_id' => $course->id, 'price_id' => $price->id,
                'amount_qepik' => $price->amount_qepik, 'currency' => $price->currency, 'purpose' => 'initial',
                'status' => 'created', 'provider' => $this->provider->name(), 'save_card' => $this->provider->supportsSavedCards()]);
            DB::table('payment_order_owners')->insert(['order_id' => $o->id, 'user_id' => $user->id]);

            return $o;
        });
        $res = $this->provider->createPayment($order, route('billing.return', $order), route('billing.return', $order),
            'Marketing School — '.$course->title().' (1 ay)');
        // Açıq yönləndirmənin qarşısı: yalnız icazəli ödəniş domenlərinə (provayder cavabı saxtalaşdırılsa belə)
        $host = parse_url($res['redirect_url'], PHP_URL_HOST);
        $allowed = array_merge(array_map(fn ($u) => parse_url($u, PHP_URL_HOST), (array) config('services.payment.form_action_hosts')), ['pay.fake.test']);
        abort_unless(str_starts_with($res['redirect_url'], 'https://') && in_array($host, $allowed, true), 502, 'unexpected payment redirect');
        DbContext::as($user->id, fn () => $order->update(['status' => 'pending', 'provider_order_id' => $res['provider_order_id']]));
        Audit::record('billing.checkout_started', null, after: ['order' => $order->id, 'amount' => $order->amount_qepik], actorId: $user->id, entityType: 'PaymentOrder');

        return $res['redirect_url'];
    }

    public static function ownerOf(string $orderId): ?int
    {
        $id = DB::table('payment_order_owners')->where('order_id', $orderId)->value('user_id');

        return $id ? (int) $id : null;
    }

    /** Provayderdən server-to-server status alır və tətbiq edir (webhook, return səhifəsi, reconcile). */
    public function confirm(PaymentOrder $order): string
    {
        $s = $this->provider->fetchStatus($order);

        return $this->apply($order, $s);
    }

    public function apply(PaymentOrder $order, ProviderStatus $s): string
    {
        return match ($s->status) {
            'paid' => $this->applyPaid($order, $s),
            'failed' => $this->markFailed($order, $s),
            'refunded' => $this->applyReversal($order, $s, 'refunded'),
            'charged_back' => $this->applyReversal($order, $s, 'charged_back'),
            default => 'pending',
        };
    }

    private function applyPaid(PaymentOrder $order, ProviderStatus $s): string
    {
        return DbContext::as($order->user_id, fn () => DB::transaction(function () use ($order, $s) {
            $order = PaymentOrder::lockForUpdate()->findOrFail($order->id);
            if (! $order->canMoveTo('paid')) {
                return 'ignored';   // artıq tətbiq olunub və ya daha irəli statusdadır (ardıcıllıqdan kənar/təkrar hadisə)
            }
            if ($s->amountQepik !== null && $s->amountQepik !== $order->amount_qepik) {
                Log::error('payment amount mismatch', ['order' => $order->id]);
                Audit::record('billing.amount_mismatch', null, after: ['order' => $order->id, 'expected' => $order->amount_qepik, 'got' => $s->amountQepik], entityType: 'PaymentOrder');

                return 'amount_mismatch';
            }
            try {
                $txn = DB::transaction(fn () => PaymentTransaction::create(['order_id' => $order->id, 'user_id' => $order->user_id, 'course_id' => $order->course_id,
                    'provider_txn_id' => $s->txnId ?? 'order:'.$order->id, 'kind' => 'charge', 'amount_qepik' => $order->amount_qepik,
                    'raw_status' => $s->rawStatus, 'occurred_at' => now()]));
            } catch (UniqueConstraintViolationException) {
                return 'ignored';
            }
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $user = User::find($order->user_id);
            $course = Course::find($order->course_id);
            $e = $this->grantMonth($user, $course, 'payment', $txn->id);
            $this->syncSubscription($e, $order, $s);
            DemoService::markConverted($user, $course);
            Notifier::send($user, 'payment_succeeded', ['course' => tr_field($course->name, $user->locale), 'amount' => Price::find($order->price_id)?->display(),
                'until' => Tz::fmt($e->paid_until, 'd.m.Y'), 'url' => route('course.show', $course)],
                ['dedupe' => $order->id, 'priority' => Notifier::P_BILLING]);
            Audit::record('billing.payment_applied', $e, after: ['order' => $order->id, 'paid_until' => $e->paid_until->toIso8601String()], actorId: $order->user_id);

            return 'applied';
        }));
    }

    /**
     * Bir aylıq giriş dövrü. 1c-7 (default extend_from_paid_until): grace daxilində ödənsə köhnə paid_until-dən
     * bir ay əlavə olunur (tələbə günlərini itirmir); expired/yeni olanda ödəniş anından.
     */
    public function grantMonth(User $user, Course $course, string $source, ?int $txnId, ?User $by = null, ?string $reason = null, int $months = 1): Enrollment
    {
        $e = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->lockForUpdate()->first();
        $status = $this->access->status($e);
        $rule = BillingRule::for($course->id)->grace_payment_rule;
        $start = ($e && $e->paid_until && in_array($status, [AccessPolicy::ACTIVE, AccessPolicy::PAST_DUE], true) && $rule === 'extend_from_paid_until')
            ? CarbonImmutable::instance($e->paid_until) : CarbonImmutable::now();
        if (! $e) {
            $e = Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'course_version_id' => $course->current_version_id]);
        }
        AccessPeriod::create(['enrollment_id' => $e->id, 'user_id' => $user->id, 'course_id' => $course->id, 'source' => $source,
            'transaction_id' => $txnId, 'starts_at' => $start, 'ends_at' => $start->addMonthsNoOverflow($months),
            'created_by' => $by?->id, 'reason' => $reason, 'created_at' => now()]);
        $e->canceled_at = null;
        $e->cancel_reason = null;
        $this->recalc($e);
        // Kursa qayıdan tələbə qaldığı yerdən davam edir: progress user+lesson-a bağlıdır, toxunulmur.
        // Milestone-lar (1:1) yenidən yoxlanılır.
        app(ProgressService::class)->recomputeMilestones($user, $course);

        return $e->fresh();
    }

    public function recalc(Enrollment $e): void
    {
        $max = AccessPeriod::where('enrollment_id', $e->id)->whereNull('revoked_at')->max('ends_at');
        $e->paid_until = $max;
        $e->status_cached = $this->access->status($e);
        $e->save();
    }

    private function syncSubscription(Enrollment $e, PaymentOrder $order, ProviderStatus $s): void
    {
        $sub = Subscription::where('enrollment_id', $e->id)->where('status', '<>', 'canceled')->lockForUpdate()->first()
            ?? new Subscription(['enrollment_id' => $e->id, 'user_id' => $e->user_id, 'course_id' => $e->course_id, 'provider' => $this->provider->name()]);
        if ($s->cardToken) {
            $sub->provider_ref_encrypted = $s->cardToken;
            $sub->card_last4 = $s->cardLast4;
        }
        $sub->fill(['status' => 'active', 'next_charge_at' => $e->paid_until, 'failed_attempts' => 0]);
        if ($order->purpose === 'initial') {
            $sub->cancel_at_period_end = false;   // yeni ödəniş = yenilənməyə razılıq
        }
        $sub->save();
        $order->update(['subscription_id' => $sub->id]);
    }

    private function markFailed(PaymentOrder $order, ProviderStatus $s): string
    {
        return DbContext::as($order->user_id, fn () => DB::transaction(function () use ($order, $s) {
            $order = PaymentOrder::lockForUpdate()->findOrFail($order->id);
            if (! $order->canMoveTo('failed')) {
                return 'ignored';
            }
            $order->update(['status' => 'failed']);
            if ($order->subscription_id && $sub = Subscription::lockForUpdate()->find($order->subscription_id)) {
                $sub->update(['status' => 'past_due', 'failed_attempts' => $sub->failed_attempts + 1]);
            }
            $user = User::find($order->user_id);
            Notifier::send($user, 'payment_failed', ['course' => tr_field(Course::find($order->course_id)->name, $user->locale),
                'url' => route('dashboard')], ['dedupe' => $order->id.'fail', 'priority' => Notifier::P_BILLING]);
            Audit::record('billing.payment_failed', null, after: ['order' => $order->id, 'raw' => $s->rawStatus], entityType: 'PaymentOrder', actorId: $order->user_id);

            return 'failed';
        }));
    }

    /**
     * Refund / chargeback. 1c-6 default (revoke_covered_period): ödənişin əhatə etdiyi dövr ləğv edilir;
     * başqa etibarlı dövr qalmasa enrollment `canceled` olur. Akademik məlumat və diplom saxlanılır.
     */
    private function applyReversal(PaymentOrder $order, ProviderStatus $s, string $to): string
    {
        return DbContext::as($order->user_id, fn () => DB::transaction(function () use ($order, $s, $to) {
            $order = PaymentOrder::lockForUpdate()->findOrFail($order->id);
            if (! $order->canMoveTo($to) || $order->status !== 'paid') {
                return 'ignored';
            }
            $charge = PaymentTransaction::where('order_id', $order->id)->where('kind', 'charge')->first();
            try {
                DB::transaction(fn () => PaymentTransaction::create(['order_id' => $order->id, 'user_id' => $order->user_id, 'course_id' => $order->course_id,
                    'provider_txn_id' => $s->txnId ?? $to.':'.$order->id, 'kind' => $to === 'refunded' ? 'refund' : 'chargeback',
                    'amount_qepik' => -1 * ($s->amountQepik ?? $order->amount_qepik), 'raw_status' => $s->rawStatus, 'occurred_at' => now()]));
            } catch (UniqueConstraintViolationException) {
                return 'ignored';
            }
            $order->update(['status' => $to]);
            $e = Enrollment::where('user_id', $order->user_id)->where('course_id', $order->course_id)->lockForUpdate()->first();
            if ($e && BillingRule::for($order->course_id)->refund_access_rule === 'revoke_covered_period' && $charge) {
                AccessPeriod::where('transaction_id', $charge->id)->update(['revoked_at' => now(), 'revoke_reason' => $to]);
                $this->recalc($e);
                if (! $e->paid_until || $e->paid_until->isPast()) {
                    $e->update(['canceled_at' => now(), 'cancel_reason' => $to === 'refunded' ? 'refund' : 'chargeback']);
                }
            }
            if ($to === 'charged_back' && $e) {
                Subscription::where('enrollment_id', $e->id)->where('status', '<>', 'canceled')
                    ->update(['status' => 'canceled', 'canceled_at' => now(), 'provider_ref_encrypted' => null, 'cancel_at_period_end' => true]);
            }
            $user = User::find($order->user_id);
            Notifier::send($user, 'refund_processed', ['course' => tr_field(Course::find($order->course_id)->name, $user->locale), 'url' => route('dashboard')],
                ['dedupe' => $order->id.$to, 'priority' => Notifier::P_BILLING]);
            Audit::record('billing.'.$to, $e, after: ['order' => $order->id, 'paid_until' => $e?->paid_until?->toIso8601String()], actorId: $order->user_id);

            return $to;
        }));
    }

    /** Admin refund-u: provayderə sorğu, sonra eyni yoldan tətbiq. */
    public function refund(PaymentOrder $order, User $admin, string $reason): string
    {
        abort_unless($admin->isAdmin() && trim($reason) !== '', 422);
        $s = $this->provider->refund($order, $order->amount_qepik);
        Audit::record('billing.refund_requested', null, after: ['order' => $order->id], reason: $reason, actorId: $admin->id, entityType: 'PaymentOrder');

        return $s->status === 'refunded' ? $this->applyReversal($order, $s, 'refunded') : 'failed';
    }

    /** Avtomatik ödənişin dayandırılması: gələcək çıxarışlar dayanır, ödənilmiş dövr ləğv edilmir. */
    public function cancelAutoRenew(Subscription $sub, User $by): void
    {
        $rule = BillingRule::for($sub->course_id);
        if ($rule->min_commitment_months && ! $by->isAdmin()) {
            $months = AccessPeriod::where('enrollment_id', $sub->enrollment_id)->whereNull('revoked_at')->where('source', 'payment')->count();
            if ($months < $rule->min_commitment_months) {
                throw ValidationException::withMessages(['subscription' => __('billing.min_commitment', ['n' => $rule->min_commitment_months])]);
            }
        }
        $sub->update(['cancel_at_period_end' => true]);
        $e = Enrollment::find($sub->enrollment_id);
        $user = User::find($sub->user_id);
        Notifier::send($user, 'subscription_canceled', ['course' => tr_field(Course::find($sub->course_id)->name, $user->locale),
            'until' => Tz::fmt($e->paid_until, 'd.m.Y'), 'url' => route('dashboard')], ['entity' => ['Subscription', $sub->id], 'dedupe' => now()->toDateString()]);
        Audit::record('billing.auto_renew_canceled', $sub, actorId: $by->id);
    }

    public function resumeAutoRenew(Subscription $sub, User $by): void
    {
        $sub->update(['cancel_at_period_end' => false]);
        Audit::record('billing.auto_renew_resumed', $sub, actorId: $by->id);
    }

    /** Mərhələ 1: admin əl ilə aktivləşdirmə (məcburi səbəb, audit). */
    public function manualActivate(User $admin, User $student, Course $course, int $months, string $reason): Enrollment
    {
        abort_unless($admin->isAdmin(), 403);
        if (trim($reason) === '' || $months < 1 || $months > 24) {
            throw ValidationException::withMessages(['reason' => __('admin.reason_required')]);
        }

        return DbContext::as($admin->id, fn () => DB::transaction(function () use ($admin, $student, $course, $months, $reason) {
            $e = $this->grantMonth($student, $course, 'manual', null, $admin, $reason, $months);
            DemoService::markConverted($student, $course);
            Audit::record('billing.manual_activation', $e, after: ['months' => $months, 'paid_until' => $e->paid_until->toIso8601String()], reason: $reason, actorId: $admin->id);

            return $e;
        }));
    }

    /** Scheduler: vaxtı çatmış abunələr üçün saxlanmış kartla çıxarış (cancel_at_period_end olmayanlar). */
    public function renewDue(): array
    {
        $out = ['charged' => 0, 'failed' => 0, 'skipped' => 0];
        $subs = Subscription::whereIn('status', ['active', 'past_due'])->where('cancel_at_period_end', false)
            ->where('next_charge_at', '<=', now())->get();
        foreach ($subs as $sub) {
            $e = Enrollment::find($sub->enrollment_id);
            if (! $sub->provider_ref_encrypted || ! $this->provider->supportsSavedCards() || $this->access->status($e) === AccessPolicy::EXPIRED || $e->canceled_at) {
                $out['skipped']++;

                continue;
            }
            // Gündə ən çox bir cəhd (idempotent)
            if (PaymentOrder::where('subscription_id', $sub->id)->where('purpose', 'renewal')->where('created_at', '>', now()->subHours(20))->exists()) {
                $out['skipped']++;

                continue;
            }
            $price = Price::current($sub->course_id);
            $order = PaymentOrder::create(['user_id' => $sub->user_id, 'course_id' => $sub->course_id, 'price_id' => $price->id,
                'amount_qepik' => $price->amount_qepik, 'currency' => $price->currency, 'purpose' => 'renewal', 'status' => 'pending',
                'provider' => $this->provider->name(), 'save_card' => false, 'subscription_id' => $sub->id]);
            DB::table('payment_order_owners')->insert(['order_id' => $order->id, 'user_id' => $sub->user_id]);
            try {
                $s = $this->provider->chargeSavedCard($order, $sub->provider_ref_encrypted, 'Marketing School — aylıq ödəniş');
            } catch (\Throwable $ex) {
                $s = new ProviderStatus('failed', rawStatus: 'error');
            }
            $r = $this->apply($order, $s);
            $out[$r === 'applied' ? 'charged' : 'failed']++;
        }

        return $out;
    }

    /** Scheduler: enrollment statuslarının keçidləri (active→past_due→expired) və ödəniş xatırlatmaları. */
    public function refreshStatuses(): int
    {
        $n = 0;
        foreach (Enrollment::whereNotNull('paid_until')->where('paid_until', '>', now()->subDays(40))->get() as $e) {
            $new = $this->access->status($e);
            $old = $e->status_cached;
            $user = User::find($e->user_id);
            $course = Course::find($e->course_id);
            if ($old !== $new) {
                $e->update(['status_cached' => $new]);
                $data = ['course' => tr_field($course->name, $user->locale), 'until' => Tz::fmt($this->access->graceUntil($e)), 'url' => route('dashboard')];
                if ($old === AccessPolicy::ACTIVE && $new === AccessPolicy::PAST_DUE) {
                    Notifier::send($user, 'grace_started', $data, ['entity' => ['Enrollment', $e->id], 'dedupe' => 'gs'.$e->paid_until->timestamp, 'priority' => Notifier::P_BILLING]);
                } elseif ($new === AccessPolicy::EXPIRED && in_array($old, [AccessPolicy::PAST_DUE, AccessPolicy::ACTIVE], true)) {
                    Notifier::send($user, 'grace_ended', $data, ['entity' => ['Enrollment', $e->id], 'dedupe' => 'ge'.$e->paid_until->timestamp, 'priority' => Notifier::P_BILLING]);
                }
                $n++;
            }
            // Növbəti ödənişə 5 gün qalması (saxlanmış kart yoxdursa — ödəniş linki)
            $sub = Subscription::where('enrollment_id', $e->id)->where('status', '<>', 'canceled')->first();
            if ($new === AccessPolicy::ACTIVE && $e->paid_until->between(now(), now()->addDays(5)) && (! $sub || ! $sub->cancel_at_period_end)) {
                $auto = $sub && $sub->provider_ref_encrypted && $this->provider->supportsSavedCards();
                Notifier::send($user, $auto ? 'renewal_upcoming' : 'pay_link', ['course' => tr_field($course->name, $user->locale),
                    'until' => Tz::fmt($e->paid_until, 'd.m.Y'), 'amount' => Price::current($course->id)?->display(), 'url' => route('course.show', $course)],
                    ['entity' => ['Enrollment', $e->id], 'dedupe' => 'r'.$e->paid_until->timestamp, 'channels' => ['email', 'in_app'], 'priority' => Notifier::P_BILLING]);
            }
        }

        return $n;
    }
}
