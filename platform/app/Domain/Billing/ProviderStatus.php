<?php

namespace App\Domain\Billing;

/** Provayderdən təsdiqlənmiş vəziyyət (webhook və ya status sorğusu). */
final class ProviderStatus
{
    public function __construct(
        public readonly string $status,          // paid | failed | pending | refunded | charged_back
        public readonly ?string $txnId = null,
        public readonly ?int $amountQepik = null,
        public readonly ?string $cardToken = null,
        public readonly ?string $cardLast4 = null,
        public readonly ?string $rawStatus = null,
    ) {}
}
