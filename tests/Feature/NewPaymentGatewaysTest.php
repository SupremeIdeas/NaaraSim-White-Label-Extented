<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payments\BinancePayGateway;
use App\Services\Payments\PaypalGateway;
use App\Support\PaymentBrandIcons;
use App\Support\ProviderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Added wallet-funding gateways (Module 27.5): PayPal (Orders v2) and Binance
 * Pay (merchant v3). Both follow the documented API shapes and the same money-
 * safety discipline — verify the webhook BEFORE crediting, idempotent on the
 * reference. Real brand logos are saved (not generated) via PaymentBrandIcons.
 */
class NewPaymentGatewaysTest extends TestCase
{
    use RefreshDatabase;

    private function postRaw(string $uri, string $content, array $headers = []): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server, $content);
    }

    // ── Provider status + logos ─────────────────────────────────────────────

    public function test_new_gateways_flip_active_only_when_keys_are_present(): void
    {
        config(['services.paypal.client_id' => '', 'services.paypal.client_secret' => '']);
        config(['services.binance.api_key' => '', 'services.binance.api_secret' => '']);
        $this->assertFalse(ProviderStatus::isActive('paypal'));
        $this->assertFalse(ProviderStatus::isActive('binance'));

        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'sec']);
        config(['services.binance.api_key' => 'k', 'services.binance.api_secret' => 's']);
        $this->assertTrue(ProviderStatus::isActive('paypal'));
        $this->assertTrue(ProviderStatus::isActive('binance'));
    }

    public function test_real_brand_logos_are_saved_for_every_gateway_and_card(): void
    {
        foreach (['paystack', 'flutterwave', 'stripe', 'paypal', 'binance', 'visa', 'mastercard', 'googlepay', 'applepay'] as $slug) {
            $this->assertTrue(PaymentBrandIcons::has($slug), "missing logo: {$slug}");
            $this->assertStringContainsString('<svg', (string) PaymentBrandIcons::svg($slug));
        }
    }

    // ── PayPal ──────────────────────────────────────────────────────────────

    public function test_paypal_initialize_returns_the_approve_link(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'sec', 'services.paypal.base_url' => 'https://api-m.paypal.com']);
        Http::fake([
            'api-m.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'AT']),
            'api-m.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER1',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://x/self'],
                    ['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=ORDER1'],
                ],
            ]),
        ]);
        $user = User::factory()->create();

        $result = app(PaypalGateway::class)->initialize($user, 20.0, 'USD');

        $this->assertSame('https://www.paypal.com/checkoutnow?token=ORDER1', $result['redirect_url']);
        $this->assertStringStartsWith('NAARA-', $result['reference']);
    }

    private function paypalBody(User $user, string $ref = 'NAARA-PP-1'): string
    {
        return json_encode([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'custom_id' => $user->id.':'.$ref,
                'amount' => ['value' => '20.00', 'currency_code' => 'USD'],
            ],
        ]);
    }

    public function test_paypal_webhook_is_rejected_when_paypal_says_the_signature_is_invalid(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'sec', 'services.paypal.webhook_id' => 'WH1']);
        Http::fake(['api-m.paypal.com/*' => Http::response(['verification_status' => 'FAILURE'])]);
        $user = User::factory()->create();

        $this->postRaw('/webhooks/payments/paypal', $this->paypalBody($user), ['paypal-transmission-sig' => 'x'])
            ->assertStatus(401);
        $this->assertSame(0, WalletTransaction::where('reference', 'topup:paypal:NAARA-PP-1')->count());
    }

    public function test_paypal_webhook_credits_once_when_verified(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'sec', 'services.paypal.webhook_id' => 'WH1']);
        Http::fake([
            'api-m.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'AT']),
            'api-m.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);
        $user = User::factory()->create();
        $body = $this->paypalBody($user);

        $this->postRaw('/webhooks/payments/paypal', $body, ['paypal-transmission-sig' => 'x'])->assertOk();
        $this->postRaw('/webhooks/payments/paypal', $body, ['paypal-transmission-sig' => 'x'])->assertOk();

        $this->assertSame(1, WalletTransaction::where('reference', 'topup:paypal:NAARA-PP-1')->count());
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    // ── Binance Pay ─────────────────────────────────────────────────────────

    private function binanceSign(string $ts, string $nonce, string $body): string
    {
        return strtoupper(hash_hmac('sha512', $ts."\n".$nonce."\n".$body."\n", (string) config('services.binance.api_secret')));
    }

    public function test_binance_initialize_signs_and_returns_the_checkout_url(): void
    {
        config(['services.binance.api_key' => 'k', 'services.binance.api_secret' => 'topsecret', 'services.binance.base_url' => 'https://bpay.binanceapi.com']);
        Http::fake([
            'bpay.binanceapi.com/*' => Http::response(['status' => 'SUCCESS', 'data' => ['universalUrl' => 'https://app.binance.com/pay/xyz']]),
        ]);
        $user = User::factory()->create();

        $result = app(BinancePayGateway::class)->initialize($user, 20.0, 'USDT');

        $this->assertSame('https://app.binance.com/pay/xyz', $result['redirect_url']);
        // The request went out signed.
        Http::assertSent(fn ($req) => $req->hasHeader('BinancePay-Signature') && $req->hasHeader('BinancePay-Timestamp'));
    }

    public function test_binance_webhook_credits_only_on_valid_signature(): void
    {
        config(['services.binance.api_key' => 'k', 'services.binance.api_secret' => 'topsecret']);
        $user = User::factory()->create();

        $data = json_encode([
            'merchantTradeNo' => 'NAARABIN1',
            'orderAmount' => 20.0,
            'currency' => 'USDT',
            'passThroughInfo' => json_encode(['user_id' => $user->id, 'reference' => 'NAARA-BIN-1']),
        ]);
        $body = json_encode(['bizStatus' => 'PAY_SUCCESS', 'data' => $data]);
        $ts = '1700000000000';
        $nonce = str_repeat('a', 32);

        // Wrong signature → rejected.
        $this->postRaw('/webhooks/payments/binance', $body, [
            'BinancePay-Timestamp' => $ts, 'BinancePay-Nonce' => $nonce, 'BinancePay-Signature' => 'WRONG',
        ])->assertStatus(401);
        $this->assertSame(0, WalletTransaction::where('reference', 'topup:binance:NAARA-BIN-1')->count());

        // Correct signature → credited once (idempotent).
        $sig = $this->binanceSign($ts, $nonce, $body);
        $headers = ['BinancePay-Timestamp' => $ts, 'BinancePay-Nonce' => $nonce, 'BinancePay-Signature' => $sig];
        $this->postRaw('/webhooks/payments/binance', $body, $headers)->assertOk();
        $this->postRaw('/webhooks/payments/binance', $body, $headers)->assertOk();
        $this->assertSame(1, WalletTransaction::where('reference', 'topup:binance:NAARA-BIN-1')->count());
    }
}
