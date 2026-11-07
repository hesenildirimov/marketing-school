<?php

namespace App\Domain\Billing;

final class WebhookEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $orderRef,       // bizim payment_orders.id
        public readonly ProviderStatus $claimed, // webhook-un iddiası (status sorğusu ilə təsdiqlənir)
        public readonly array $redacted,
    ) {}
}
