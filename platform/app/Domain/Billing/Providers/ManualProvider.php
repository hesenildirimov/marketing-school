<?php

namespace App\Domain\Billing\Providers;

use App\Domain\Billing\InvalidSignature;
use App\Domain\Billing\PaymentProvider;
use App\Domain\Billing\ProviderStatus;
use App\Domain\Billing\WebhookEvent;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;

/** Mərhələ 1: onlayn ödəniş yoxdur — admin enrollment-i əl ilə aktivləşdirir (səbəb + audit). */
final class ManualProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function supportsSavedCards(): bool
    {
        return false;
    }

    public function createPayment(PaymentOrder $order, string $successUrl, string $errorUrl, string $description): array
    {
        abort(503, __('billing.manual_only'));
    }

    public function chargeSavedCard(PaymentOrder $order, string $cardToken, string $description): ProviderStatus
    {
        return new ProviderStatus('failed', rawStatus: 'manual');
    }

    public function fetchStatus(PaymentOrder $order): ProviderStatus
    {
        return new ProviderStatus('pending');
    }

    public function refund(PaymentOrder $order, int $amountQepik): ProviderStatus
    {
        return new ProviderStatus('refunded', 'manual-'.$order->id, $amountQepik);
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        throw new InvalidSignature('manual provider has no webhooks');
    }
}
