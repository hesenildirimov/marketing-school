<?php

namespace Tests\Unit;

use App\Domain\Billing\InvalidSignature;
use App\Domain\Billing\Providers\EpointProvider;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpointProviderTest extends TestCase
{
    private function provider(): EpointProvider
    {
        return new EpointProvider(['public_key' => 'i000000001', 'private_key' => 'secret-pk', 'api_url' => 'https://epoint.az/api/1']);
    }

    public function test_signature_is_base64_of_raw_sha1(): void
    {
        $data = base64_encode('{"a":1}');
        $this->assertSame(base64_encode(sha1('secret-pk'.$data.'secret-pk', true)), EpointProvider::sign($data, 'secret-pk'));
        $this->assertSame(28, strlen(EpointProvider::sign($data, 'secret-pk')), 'xam 20 bayt → base64 28 simvol (hex deyil)');
    }

    public function test_callback_signature_verified_and_mapped(): void
    {
        $data = base64_encode(json_encode(['order_id' => 'o-1', 'status' => 'success', 'transaction' => 'te001', 'amount' => '99.00', 'card_mask' => '4169********1234', 'card_id' => 'cid-9']));
        $ev = $this->provider()->parseWebhook(Request::create('/', 'POST', ['data' => $data, 'signature' => EpointProvider::sign($data, 'secret-pk')]));
        $this->assertSame('o-1', $ev->orderRef);
        $this->assertSame('paid', $ev->claimed->status);
        $this->assertSame(9900, $ev->claimed->amountQepik);
        $this->assertSame('1234', $ev->claimed->cardLast4);
        $this->assertArrayNotHasKey('card_id', $ev->redacted);
        $this->expectException(InvalidSignature::class);
        $this->provider()->parseWebhook(Request::create('/', 'POST', ['data' => $data, 'signature' => 'forged']));
    }

    public function test_create_payment_sends_signed_server_side_amount(): void
    {
        Http::fake(['epoint.az/*' => Http::response(['status' => 'success', 'redirect_url' => 'https://epoint.az/pay/x', 'transaction' => 'te1'])]);
        $o = new PaymentOrder(['amount_qepik' => 9900, 'currency' => 'AZN', 'save_card' => true]);
        $o->id = 'order-uuid';
        $r = $this->provider()->createPayment($o, 'https://s', 'https://e', 'Kurs');
        $this->assertSame('https://epoint.az/pay/x', $r['redirect_url']);
        Http::assertSent(function ($req) {
            $payload = json_decode(base64_decode($req['data']), true);

            return $payload['amount'] === '99.00' && $payload['order_id'] === 'order-uuid' && $req['signature'] === EpointProvider::sign($req['data'], 'secret-pk')
                && str_ends_with($req->url(), '/card-registration-with-pay');
        });
    }
}
