<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessPolicy;
use App\Domain\Audit\Audit;
use App\Domain\Billing\BillingService;
use App\Domain\Billing\InvalidSignature;
use App\Models\Course;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Support\DbContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function __construct(private BillingService $billing) {}

    /** Məbləğ müştəridən qəbul edilmir — yalnız kurs. */
    public function checkout(Request $request, Course $course, AccessPolicy $access)
    {
        abort_if($access->status($access->enrollment($request->user(), $course)) === AccessPolicy::ACTIVE
            && Subscription::where('user_id', $request->user()->id)->where('course_id', $course->id)->where('status', 'active')->where('cancel_at_period_end', false)->exists(), 409, __('billing.already_active'));

        return redirect()->away($this->billing->checkout($request->user(), $course));
    }

    /** Provayderin redirect-i: nəticəyə təkbaşına etibar edilmir, server-to-server status sorğusu edilir. */
    public function return(Request $request, PaymentOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        if (in_array($order->status, ['created', 'pending'], true)) {
            try {
                $this->billing->confirm($order);
            } catch (\Throwable $e) {
                Log::warning('return confirm failed', ['order' => $order->id]);
            }
        }

        return view('billing.return', ['order' => $order->fresh(), 'course' => $order->course]);
    }

    public function status(Request $request, PaymentOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['status' => $order->status]);
    }

    public function cancelAutoRenew(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $this->billing->cancelAutoRenew($subscription, $request->user());

        return back()->with('status', __('billing.auto_renew_off'));
    }

    public function resumeAutoRenew(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $this->billing->resumeAutoRenew($subscription, $request->user());

        return back()->with('status', __('billing.auto_renew_on'));
    }

    /**
     * Webhook: imza yoxlanılır → hadisə UNIQUE(provider, event_id) ilə saxlanılır (təkrar → heç nə) →
     * sifarişin sahibi kontekstində server-to-server status sorğusu ilə təsdiq və tətbiq.
     * Emal uğursuz olsa hadisə `failed` qalır, worker yenidən cəhd edir, admin paneldə görünür.
     */
    public function webhook(Request $request, string $provider)
    {
        $p = $this->billing->provider();
        abort_unless($provider === $p->name(), 404);
        try {
            $ev = $p->parseWebhook($request);
        } catch (InvalidSignature) {
            Log::warning('webhook bad signature', ['provider' => $provider]);
            Audit::record('billing.webhook_bad_signature', null, after: ['provider' => $provider], entityType: 'PaymentWebhookEvent');

            return response()->json(['error' => 'invalid_signature'], 400);
        }
        $inserted = DB::table('payment_webhook_events')->insertOrIgnore(['provider' => $provider, 'provider_event_id' => $ev->eventId,
            'signature_valid' => true, 'payload_redacted' => json_encode($ev->redacted), 'status' => 'received', 'received_at' => now()]);
        if (! $inserted) {
            return response()->json(['status' => 'duplicate']);
        }
        $event = PaymentWebhookEvent::where(['provider' => $provider, 'provider_event_id' => $ev->eventId])->first();
        self::process($event, $ev->orderRef, $this->billing);

        return response()->json(['status' => 'ok']);
    }

    public static function process(PaymentWebhookEvent $event, string $orderRef, BillingService $billing): void
    {
        try {
            $owner = BillingService::ownerOf($orderRef) ?? throw new \RuntimeException('unknown order');
            $result = DbContext::as($owner, function () use ($orderRef, $billing) {
                $order = PaymentOrder::findOrFail($orderRef);

                return $billing->confirm($order);
            });
            $event->update(['status' => $result === 'pending' ? 'received' : 'processed', 'processed_at' => now(), 'attempts' => $event->attempts + 1, 'last_error' => null]);
        } catch (\Throwable $e) {
            $event->update(['status' => 'failed', 'attempts' => $event->attempts + 1, 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::error('webhook processing failed', ['event' => $event->id, 'error' => $e->getMessage()]);
        }
    }
}
