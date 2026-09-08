<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payments\CryptomusGateway;
use App\Services\Payments\NowPaymentsGateway;
use App\Support\ProviderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Crypto / alt payment gateways (blueprint Section 14.2): NOWPayments, Cryptomus,
 * CoinPayments, Payssion. Each follows the documented API shape and the same
 * money-safety discipline — verify the webhook signature BEFORE crediting,
 * idempotent per (gateway, reference), user carried in the order id.
 */
class CryptoGatewaysTest extends TestCase
{
    use RefreshDatabase;

    private function postRaw(string $uri, string $content, array $headers = [], string $type = 'application/json'): TestResponse
    {
        $server = ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $this->call('POST', $uri, [], [], [], $server, $content);
    }

    private function balanceKey(string $gw, string $ref): string
    {
        return "topup:{$gw}:{$ref}";
    }

    /** CoinPayments IPN: form fields populate input() AND the raw body (getContent) for the HMAC. */
    private function coinPaymentsIpn(array $fields, string $hmac): TestResponse
    {
        $server = [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HMAC' => $hmac,
        ];

        return $this->call('POST', '/webhooks/payments/coinpayments', $fields, [], [], $server, http_build_query($fields));
    }

    // ── Provider gating ─────────────────────────────────────────────────────

    public function test_each_gateway_flips_active_only_with_its_keys(): void
    {
        config([
            'services.nowpayments.api_key' => 'k',
            'services.cryptomus.merchant_id' => 'm', 'services.cryptomus.api_key' => 'k',
            'services.coinpayments.public_key' => 'p', 'services.coinpayments.private_key' => 'v',
            'services.payssion.api_key' => 'k', 'services.payssion.secret_key' => 's',
        ]);
        foreach (['nowpayments', 'cryptomus', 'coinpayments', 'payssion'] as $gw) {
            $this->assertTrue(ProviderStatus::isActive($gw), "$gw should be active");
        }

        config(['services.nowpayments.api_key' => '']);
        $this->assertFalse(ProviderStatus::isActive('nowpayments'));
    }

    // ── NOWPayments ─────────────────────────────────────────────────────────

    public function test_nowpayments_initialize_and_verified_ipn_credits_once(): void
    {
        config(['services.nowpayments.api_key' => 'k', 'services.nowpayments.ipn_secret' => 'ipnsecret']);
        Http::fake(['api.nowpayments.io/*' => Http::response(['id' => 'INV1', 'invoice_url' => 'https://nowpayments.io/pay/INV1'])]);
        $user = User::factory()->create();

        $init = app(NowPaymentsGateway::class)->initialize($user, 20.0, 'USD');
        $this->assertSame('https://nowpayments.io/pay/INV1', $init['redirect_url']);

        $payload = ['payment_status' => 'finished', 'order_id' => $user->id.':NAARA-NP-1', 'price_amount' => 20, 'price_currency' => 'usd'];
        $sig = hash_hmac('sha512', json_encode($this->ksort($payload), JSON_UNESCAPED_SLASHES), 'ipnsecret');

        $body = json_encode($payload);
        $this->postRaw('/webhooks/payments/nowpayments', $body, ['x-nowpayments-sig' => $sig])->assertOk();
        $this->postRaw('/webhooks/payments/nowpayments', $body, ['x-nowpayments-sig' => $sig])->assertOk();

        $this->assertSame(1, WalletTransaction::where('reference', $this->balanceKey('nowpayments', 'NAARA-NP-1'))->count());
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);

        // Wrong signature → rejected.
        $this->postRaw('/webhooks/payments/nowpayments', json_encode(['payment_status' => 'finished', 'order_id' => $user->id.':NAARA-NP-2', 'price_amount' => 5, 'price_currency' => 'usd']), ['x-nowpayments-sig' => 'bad'])->assertStatus(401);
        $this->assertSame(0, WalletTransaction::where('reference', $this->balanceKey('nowpayments', 'NAARA-NP-2'))->count());
    }

    private function ksort(array $a): array
    {
        ksort($a);
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = $this->ksort($v);
            }
        }

        return $a;
    }

    // ── Cryptomus ───────────────────────────────────────────────────────────

    public function test_cryptomus_initialize_and_verified_webhook_credits(): void
    {
        config(['services.cryptomus.merchant_id' => 'M1', 'services.cryptomus.api_key' => 'apikey']);
        Http::fake(['api.cryptomus.com/*' => Http::response(['result' => ['url' => 'https://pay.cryptomus.com/xyz']])]);
        $user = User::factory()->create();

        $init = app(CryptomusGateway::class)->initialize($user, 20.0, 'USD');
        $this->assertSame('https://pay.cryptomus.com/xyz', $init['redirect_url']);

        $data = ['status' => 'paid', 'order_id' => $user->id.':NAARA-CM-1', 'amount' => '20.00', 'currency' => 'USD'];
        $sign = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'apikey');
        // sign appended last so removing it leaves $data in its original order.
        $body = json_encode($data + ['sign' => $sign]);

        $this->postRaw('/webhooks/payments/cryptomus', $body)->assertOk();
        $this->assertSame(1, WalletTransaction::where('reference', $this->balanceKey('cryptomus', 'NAARA-CM-1'))->count());
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);

        // Tampered amount → sign no longer matches → rejected.
        $tampered = json_encode(['status' => 'paid', 'order_id' => $user->id.':NAARA-CM-9', 'amount' => '999.00', 'currency' => 'USD', 'sign' => $sign]);
        $this->postRaw('/webhooks/payments/cryptomus', $tampered)->assertStatus(401);
    }

    public function test_cryptomus_webhook_with_a_slash_in_the_payload_is_verified_the_canonical_way(): void
    {
        // Regression: Cryptomus signs md5(base64(json_encode($d, JSON_UNESCAPED_UNICODE)) . key)
        // — slashes ESCAPED. The old code signed with JSON_UNESCAPED_SLASHES, so
        // ANY payload containing a "/" (a network name, a URL, a txid path) would
        // have its valid signature rejected and the credit silently lost. This
        // payload contains slashes and is signed the canonical (escaped) way.
        config(['services.cryptomus.merchant_id' => 'M1', 'services.cryptomus.api_key' => 'apikey']);
        $user = User::factory()->create();

        $data = [
            'status' => 'paid',
            'order_id' => $user->id.':NAARA-CM-SL',
            'amount' => '20.00',
            'currency' => 'USD',
            'network' => 'TRON/TRC20',                         // <- contains a slash
            'url_callback' => 'https://naara.test/webhooks/payments/cryptomus',
        ];
        // Canonical Cryptomus signing: escaped slashes (no JSON_UNESCAPED_SLASHES).
        $sign = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)).'apikey');
        $body = json_encode($data + ['sign' => $sign]);

        $this->postRaw('/webhooks/payments/cryptomus', $body)->assertOk();
        $this->assertSame(1, WalletTransaction::where('reference', $this->balanceKey('cryptomus', 'NAARA-CM-SL'))->count());
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    // ── CoinPayments ────────────────────────────────────────────────────────

    public function test_coinpayments_verified_ipn_credits_and_checks_merchant(): void
    {
        config(['services.coinpayments.public_key' => 'p', 'services.coinpayments.private_key' => 'v', 'services.coinpayments.ipn_secret' => 'ipn', 'services.coinpayments.merchant_id' => 'MID1']);
        $user = User::factory()->create();

        $fields = ['status' => '100', 'custom' => $user->id.':NAARA-CP-1', 'amount1' => '20.00', 'currency1' => 'USD', 'merchant' => 'MID1'];
        $sig = hash_hmac('sha512', http_build_query($fields), 'ipn');

        $this->coinPaymentsIpn($fields, $sig)->assertOk();
        $this->assertSame(1, WalletTransaction::where('reference', $this->balanceKey('coinpayments', 'NAARA-CP-1'))->count());
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);

        // Wrong merchant id → rejected even with a valid HMAC of the body.
        $other = ['status' => '100', 'custom' => $user->id.':NAARA-CP-2', 'amount1' => '5.00', 'currency1' => 'USD', 'merchant' => 'EVIL'];
        $this->coinPaymentsIpn($other, hash_hmac('sha512', http_build_query($other), 'ipn'))->assertStatus(401);
    }

    // ── Payssion ────────────────────────────────────────────────────────────

    public function test_payssion_verified_webhook_credits(): void
    {
        config(['services.payssion.api_key' => 'ak', 'services.payssion.secret_key' => 'sk', 'services.payssion.pm_id' => 'alipay_cn']);
        $user = User::factory()->create();

        $order = $user->id.':NAARA-PS-1';
        $sig = md5(implode('|', ['ak', 'alipay_cn', '20.00', 'USD', $order, 'completed', 'sk']));
        $fields = ['pm_id' => 'alipay_cn', 'amount' => '20.00', 'currency' => 'USD', 'order_id' => $order, 'state' => 'completed', 'notify_sig' => $sig];

        $this->post('/webhooks/payments/payssion', $fields)->assertOk();
        $this->assertSame(1, WalletTransaction::where('reference', $this->balanceKey('payssion', 'NAARA-PS-1'))->count());

        // Bad signature → rejected.
        $this->post('/webhooks/payments/payssion', array_merge($fields, ['order_id' => $user->id.':NAARA-PS-2', 'notify_sig' => 'bad']))->assertStatus(401);
    }
}
