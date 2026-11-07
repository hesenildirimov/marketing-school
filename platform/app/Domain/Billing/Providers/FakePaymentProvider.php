<?php

namespace App\Domain\Billing\Providers;

use App\Domain\Billing\InvalidSignature;
use App\Domain\Billing\PaymentProvider;
use App\Domain\Billing\ProviderStatus;
use App\Domain\Billing\WebhookEvent;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;

/** Testlər və lokal inkişaf: provayderin "həqiqəti" statik xəritədə saxlanılır; webhook HMAC ilə imzalanır. */
final class FakePaymentProvider implements PaymentProvider
{
    /** @var array<string, ProviderStatus> order_id => provayderdəki real status */
    public static array $truth = [];

    public static bool $chargeSucceeds = true;

    public function name(): string
    {
        return 'fake';
    }

    public function supportsSavedCards(): bool
    {
        return true;
    }

    public static function secret(): string
    {
        return (string) config('services.payment.fake_secret', 'fake-webhook-secret');
    }

    public function createPayment(PaymentOrder $order, string $successUrl, string $errorUrl, string $description): array
    {
        self::$truth[$order->id] ??= new ProviderStatus('pending');

        return ['redirect_url' => 'https://pay.fake.test/checkout/'.$order->id, 'provider_order_id' => 'fake-'.$order->id];
    }

    public function chargeSavedCard(PaymentOrder $order, string $cardToken, string $description): ProviderStatus
    {
        return self::$truth[$order->id] = self::$chargeSucceeds
            ? new ProviderStatus('paid', 'txn-'.$order->id, $order->amount_qepik, $cardToken, '4242')
            : new ProviderStatus('failed', 'txn-'.$order->id, rawStatus: 'declined');
    }

    public function fetchStatus(PaymentOrder $order): ProviderStatus
    {
        return self::$truth[$order->id] ?? new ProviderStatus('pending');
    }

    public function refund(PaymentOrder $order, int $amountQepik): ProviderStatus
    {
        return self::$truth[$order->id] = new ProviderStatus('refunded', 'rf-'.$order->id, $amountQepik);
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $raw = $request->getContent();
        if (! hash_equals(hash_hmac('sha256', $raw, self::secret()), (string) $request->header('X-Fake-Signature'))) {
            throw new InvalidSignature('fake signature mismatch');
        }
        $p = json_decode($raw, true) ?: [];

        return new WebhookEvent((string) $p['event_id'], (string) $p['order_id'],
            new ProviderStatus($p['status'], $p['txn_id'] ?? null, $p['amount'] ?? null, $p['card_token'] ?? null, $p['last4'] ?? null),
            array_intersect_key($p, array_flip(['event_id', 'order_id', 'status', 'txn_id', 'amount'])));
    }
}
