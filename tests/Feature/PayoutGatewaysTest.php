<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\CryptomusPayoutGateway;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayPalPayoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NAARA-BUILD-22 §5 — the two new payout rails (PayPal email + Cryptomus crypto)
 * behind the existing PayoutGatewayInterface.
 */
class PayoutGatewaysTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $provider, array $over = []): PayoutAccount
    {
        return PayoutAccount::create(array_merge([
            'user_id' => $user->id,
            'type' => $provider === 'cryptomus' ? 'crypto' : 'paypal',
            'country' => 'US',
            'currency' => $provider === 'cryptomus' ? 'USDT' : 'USD',
            'account_number' => $provider === 'cryptomus' ? 'TXwallet123' : 'earner@example.com',
            'account_name' => 'Test Earner',
            'bank_code' => $provider === 'cryptomus' ? 'TRON' : 'PAYPAL',
            'provider' => $provider,
            'is_verified' => true,
        ], $over));
    }

    private function request(User $user, PayoutAccount $acct, string $provider): PayoutRequest
    {
        return PayoutRequest::create([
            'user_id' => $user->id,
            'payee_type' => 'referral',
            'payout_account_id' => $acct->id,
            'amount' => 12.3400,
            'currency' => $acct->currency,
            'source_bucket' => 'referral',
            'status' => 'processing',
            'provider' => $provider,
            'reference' => 'PO-'.$provider.'-1',
        ]);
    }

    public function test_paypal_availability_is_key_gated_and_send_maps_status(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        $gw = app(PayPalPayoutGateway::class);
        $this->assertTrue($gw->available());

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 't0ken']),
            '*/v1/payments/payouts' => Http::response(['batch_header' => ['payout_batch_id' => 'BATCH1', 'batch_status' => 'PENDING']]),
        ]);

        $user = User::factory()->create();
        $acct = $this->account($user, 'paypal');
        $res = $gw->sendTransfer($this->request($user, $acct, 'paypal'), $acct);

        $this->assertSame('processing', $res->status);
        $this->assertSame('BATCH1', $res->providerRef);
    }

    public function test_paypal_denied_batch_is_a_failed_result_not_an_exception(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 't0ken']),
            '*/v1/payments/payouts' => Http::response(['batch_header' => ['batch_status' => 'DENIED'], 'message' => 'Insufficient funds']),
        ]);

        $user = User::factory()->create();
        $acct = $this->account($user, 'paypal');
        $res = app(PayPalPayoutGateway::class)->sendTransfer($this->request($user, $acct, 'paypal'), $acct);

        $this->assertSame('failed', $res->status);
        $this->assertStringContainsString('Insufficient', (string) $res->failureReason);
    }

    public function test_cryptomus_signs_and_maps_and_verifies_its_own_webhook(): void
    {
        config([
            'services.cryptomus.merchant_id' => 'merch-uuid',
            'services.cryptomus.payout_api_key' => 'payoutkey',
        ]);
        $gw = app(CryptomusPayoutGateway::class);
        $this->assertTrue($gw->available());

        Http::fake([
            '*/v1/payout' => Http::response(['state' => 0, 'result' => ['uuid' => 'CM-1', 'status' => 'process']]),
        ]);

        $user = User::factory()->create();
        $acct = $this->account($user, 'cryptomus');
        $res = $gw->sendTransfer($this->request($user, $acct, 'cryptomus'), $acct);
        $this->assertSame('processing', $res->status);
        $this->assertSame('CM-1', $res->providerRef);

        // A webhook signed with the payout key verifies; a tampered one does not.
        $payload = ['type' => 'payout', 'order_id' => 'PO-cryptomus-1', 'status' => 'paid', 'uuid' => 'CM-1'];
        $sign = md5(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)).'payoutkey');

        $good = \Illuminate\Http\Request::create('/x', 'POST', $payload + ['sign' => $sign]);
        $this->assertTrue($gw->verifyWebhook($good));
        $event = $gw->parseWebhook($good);
        $this->assertSame('paid', $event->status);
        $this->assertSame('PO-cryptomus-1', $event->reference);

        $bad = \Illuminate\Http\Request::create('/x', 'POST', $payload + ['sign' => 'nope']);
        $this->assertFalse($gw->verifyWebhook($bad));
    }

    public function test_payout_service_lists_new_gateways_when_configured(): void
    {
        config([
            'services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret',
            'services.cryptomus.merchant_id' => 'm', 'services.cryptomus.payout_api_key' => 'k',
        ]);
        $names = app(PayoutService::class)->payoutCapableNames();
        $this->assertContains('paypal', $names);
        $this->assertContains('cryptomus', $names);
    }
}
