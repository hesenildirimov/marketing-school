<?php

namespace App\Domain\Billing\Providers;

use App\Domain\Billing\InvalidSignature;
use App\Domain\Billing\PaymentProvider;
use App\Domain\Billing\ProviderStatus;
use App\Domain\Billing\WebhookEvent;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Epoint (epoint.az). Sorğu: data = base64(JSON), signature = base64(sha1(private_key + data + private_key, raw)).
 * Callback eyni sxemlə imzalanır. DİQQƏT: endpoint adları açıq SDK-lardan götürülüb (docs/payment-providers.md) —
 * sandbox açarları ilə staging-də yoxlanmalıdır.
 */
final class EpointProvider implements PaymentProvider
{
    public function __construct(private array $cfg) {}

    public function name(): string
    {
        return 'epoint';
    }

    public function supportsSavedCards(): bool
    {
        return true;
    }

    public static function sign(string $data, string $privateKey): string
    {
        return base64_encode(sha1($privateKey.$data.$privateKey, true));
    }

    private function call(string $path, array $payload): array
    {
        $data = base64_encode(json_encode(['public_key' => $this->cfg['public_key']] + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Http::asForm()->acceptJson()->timeout(20)->post(rtrim($this->cfg['api_url'], '/').'/'.$path,
            ['data' => $data, 'signature' => self::sign($data, $this->cfg['private_key'])])->throw()->json() ?? [];
    }

    private static function amount(int $qepik): string
    {
        return number_format($qepik / 100, 2, '.', '');
    }

    public function createPayment(PaymentOrder $order, string $successUrl, string $errorUrl, string $description): array
    {
        $r = $this->call($order->save_card ? 'card-registration-with-pay' : 'request', [
            'amount' => self::amount($order->amount_qepik), 'currency' => $order->currency, 'language' => 'az',
            'order_id' => $order->id, 'description' => mb_substr($description, 0, 200),
            'success_redirect_url' => $successUrl, 'error_redirect_url' => $errorUrl,
        ]);
        abort_unless(($r['status'] ?? null) === 'success' && ! empty($r['redirect_url']), 502, 'payment provider error');

        return ['redirect_url' => $r['redirect_url'], 'provider_order_id' => $r['transaction'] ?? null];
    }

    public function chargeSavedCard(PaymentOrder $order, string $cardToken, string $description): ProviderStatus
    {
        $r = $this->call('execute-pay', ['card_id' => $cardToken, 'order_id' => $order->id, 'amount' => self::amount($order->amount_qepik),
            'currency' => $order->currency, 'language' => 'az', 'description' => mb_substr($description, 0, 200)]);

        return $this->map($r);
    }

    public function fetchStatus(PaymentOrder $order): ProviderStatus
    {
        return $this->map($this->call('get-status', ['order_id' => $order->id]));
    }

    public function refund(PaymentOrder $order, int $amountQepik): ProviderStatus
    {
        $r = $this->call('refund-request', ['order_id' => $order->id, 'amount' => self::amount($amountQepik), 'currency' => $order->currency, 'language' => 'az']);

        return new ProviderStatus(($r['status'] ?? '') === 'success' ? 'refunded' : 'failed', $r['transaction'] ?? null, $amountQepik, rawStatus: $r['status'] ?? null);
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $data = (string) $request->input('data');
        $sig = (string) $request->input('signature');
        if ($data === '' || ! hash_equals(self::sign($data, $this->cfg['private_key']), $sig)) {
            throw new InvalidSignature('epoint signature mismatch');
        }
        $p = json_decode((string) base64_decode($data, true), true) ?: [];
        $st = $this->map($p);
        $redacted = array_intersect_key($p, array_flip(['order_id', 'status', 'code', 'transaction', 'amount', 'card_mask']));

        return new WebhookEvent(($p['transaction'] ?? 'na').':'.($p['status'] ?? 'na'), (string) ($p['order_id'] ?? ''), $st, $redacted);
    }

    private function map(array $r): ProviderStatus
    {
        $status = match ($r['status'] ?? '') {
            'success' => 'paid', 'returned' => 'refunded', 'new', 'pending' => 'pending', default => 'failed',
        };
        $mask = $r['card_mask'] ?? null;

        return new ProviderStatus($status, $r['transaction'] ?? null, isset($r['amount']) ? (int) round(((float) $r['amount']) * 100) : null,
            $r['card_id'] ?? null, $mask ? substr(preg_replace('/\D/', '', $mask), -4) : null, $r['status'] ?? null);
    }
}
