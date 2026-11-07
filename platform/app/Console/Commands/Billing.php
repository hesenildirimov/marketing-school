<?php

namespace App\Console\Commands;

use App\Domain\Billing\BillingService;
use App\Http\Controllers\BillingController;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use App\Support\System;
use Illuminate\Console\Command;

/**
 * platform:billing renew       — saxlanmış kartla aylıq çıxarış
 * platform:billing statuses    — active→past_due→expired keçidləri, grace və 5 gün xatırlatmaları
 * platform:billing reconcile   — "checkout bağlanıb, ödəniş olub" halı: gözləyən sifarişləri provayderdən soruşur
 * platform:billing webhooks    — uğursuz webhook emalını yenidən cəhd edir
 */
class Billing extends Command
{
    protected $signature = 'platform:billing {task : renew|statuses|reconcile|webhooks}';

    protected $description = 'Billing fon işləri';

    public function handle(BillingService $billing): int
    {
        $out = System::run(fn () => match ($this->argument('task')) {
            'renew' => $billing->renewDue(),
            'statuses' => ['changed' => $billing->refreshStatuses()],
            'reconcile' => ['checked' => PaymentOrder::whereIn('status', ['created', 'pending'])->where('purpose', '<>', 'manual')
                ->where('updated_at', '<', now()->subMinutes(2))->where('created_at', '>', now()->subDays(3))->get()
                ->each(fn ($o) => rescue(fn () => $billing->confirm($o)))->count()],
            'webhooks' => ['retried' => PaymentWebhookEvent::whereIn('status', ['failed', 'received'])->where('attempts', '<', 10)
                ->where('received_at', '>', now()->subDays(3))->get()
                ->each(fn ($e) => BillingController::process($e, (string) ($e->payload_redacted['order_id'] ?? ''), $billing))->count()],
            default => throw new \InvalidArgumentException('unknown task'),
        });
        $this->line(json_encode($out));

        return self::SUCCESS;
    }
}
