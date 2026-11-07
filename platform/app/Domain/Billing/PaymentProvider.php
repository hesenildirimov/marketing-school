<?php

namespace App\Domain\Billing;

use App\Models\PaymentOrder;
use Illuminate\Http\Request;

/**
 * Ödəniş adapteri (bölmə 2.10): provayder dəyişsə biznes məntiqi dəyişmir.
 * Kart məlumatı platformaya heç vaxt gəlmir; yalnız provayderin tokeni və son 4 rəqəm.
 */
interface PaymentProvider
{
    public function name(): string;

    public function supportsSavedCards(): bool;

    /** @return array{redirect_url: string, provider_order_id: string|null} */
    public function createPayment(PaymentOrder $order, string $successUrl, string $errorUrl, string $description): array;

    /** Saxlanmış kartla server-to-server çıxarış (avtomatik aylıq). */
    public function chargeSavedCard(PaymentOrder $order, string $cardToken, string $description): ProviderStatus;

    /** Server-to-server status sorğusu — redirect və ya webhook-a təkbaşına etibar edilmir. */
    public function fetchStatus(PaymentOrder $order): ProviderStatus;

    public function refund(PaymentOrder $order, int $amountQepik): ProviderStatus;

    /** İmzanı yoxlayır və hadisəni oxuyur. @throws InvalidSignature */
    public function parseWebhook(Request $request): WebhookEvent;
}
