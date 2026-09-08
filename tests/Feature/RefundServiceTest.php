<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Models\PaymentCharge;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\Payments\RefundException;
use App\Services\Payments\RefundService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * BUILD-2 §7.1 — admin-triggered refunds. Provider-first, ledger-reversing,
 * idempotent, and never silently loss-making.
 */
class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private function topUp(User $user, string $gateway, string $ref, float $amount, string $currency = 'USD')
    {
        // Simulate the credited top-up exactly as CreditWalletJob writes it.
        return app(WalletService::class)->credit($user, $amount, $currency, [
            'reference' => "topup:{$gateway}:{$ref}",
            'description' => "Wallet top-up via {$gateway}",
        ]);
    }

    public function test_paystack_refund_reverses_the_wallet_and_records_it(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 'RF_1']])]);

        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-1', 50.0);
        $this->assertSame('50.0000', (string) $user->wallet->fresh()->usd_balance);

        $refund = app(RefundService::class)->refund($topup, null, 'duplicate charge');

        $this->assertSame(PaymentRefund::STATUS_DONE, $refund->status);
        $this->assertSame('RF_1', $refund->provider_refund_ref);
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance); // reversed
        $this->assertDatabaseHas('wallet_transactions', ['reference' => 'refund-reversal:paystack:PS-REF-1', 'type' => 'debit']);
    }

    public function test_a_top_up_can_only_be_refunded_once(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['id' => 'RF_2']])]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-2', 20.0);

        app(RefundService::class)->refund($topup, null, 'x');

        $this->expectException(RefundException::class);
        app(RefundService::class)->refund($topup, null, 'again');
    }

    public function test_refund_is_refused_when_the_user_already_spent_the_funds(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['id' => 'RF_3']])]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-3', 30.0);

        // Spend most of it.
        app(WalletService::class)->debit($user, 25.0, 'USD', ['reference' => 'spend:1', 'description' => 'bought an eSIM']);

        try {
            app(RefundService::class)->refund($topup, null, 'wants money back');
            $this->fail('Expected RefundException');
        } catch (RefundException $e) {
            $this->assertStringContainsStringIgnoringCase('spent', $e->getMessage());
        }

        // Wallet untouched, no Paystack refund attempted, no refund row.
        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance);
        Http::assertNothingSent();
        $this->assertSame(0, PaymentRefund::count());
    }

    public function test_a_declined_gateway_refund_does_not_touch_the_wallet(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Transaction not refundable'], 400)]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-4', 40.0);

        try {
            app(RefundService::class)->refund($topup, null, 'x');
            $this->fail('Expected RefundException');
        } catch (RefundException $e) {
            $this->assertStringContainsStringIgnoringCase('declined', $e->getMessage());
        }

        $this->assertSame('40.0000', (string) $user->wallet->fresh()->usd_balance); // unchanged
        $this->assertSame(PaymentRefund::STATUS_FAILED, PaymentRefund::first()->status);
    }

    private function chargeId(string $gateway, string $ref, string $providerChargeId): void
    {
        PaymentCharge::create([
            'gateway' => $gateway, 'reference' => $ref, 'provider_charge_id' => $providerChargeId,
            'amount' => 0, 'currency' => 'USD',
        ]);
    }

    public function test_stripe_refund_uses_the_captured_payment_intent(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_x', 'services.stripe.base_url' => 'https://api.stripe.com/v1']);
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'stripe', 'ST-1', 30.0);
        $this->chargeId('stripe', 'ST-1', 'pi_123');

        $refund = app(RefundService::class)->refund($topup, null, 'x');

        $this->assertSame(PaymentRefund::STATUS_DONE, $refund->status);
        $this->assertSame('re_1', $refund->provider_refund_ref);
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/refunds') && $req['payment_intent'] === 'pi_123');
    }

    public function test_flutterwave_refund_uses_the_captured_transaction_id(): void
    {
        config(['services.flutterwave.secret_key' => 'flw_sk', 'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3']);
        Http::fake(['api.flutterwave.com/v3/transactions/999/refund' => Http::response(['status' => 'success', 'data' => ['id' => 'rf_9']])]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'flutterwave', 'FLW-1', 12.0);
        $this->chargeId('flutterwave', 'FLW-1', '999');

        $refund = app(RefundService::class)->refund($topup, null, 'x');

        $this->assertSame(PaymentRefund::STATUS_DONE, $refund->status);
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_paypal_refund_uses_the_captured_capture_id(): void
    {
        config(['services.paypal.client_id' => 'cid', 'services.paypal.client_secret' => 'sec', 'services.paypal.base_url' => 'https://api-m.paypal.com']);
        Http::fake([
            'api-m.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.paypal.com/v2/payments/captures/CAP1/refund' => Http::response(['id' => 'RF_PP', 'status' => 'COMPLETED']),
        ]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paypal', 'PP-1', 18.0);
        $this->chargeId('paypal', 'PP-1', 'CAP1');

        $refund = app(RefundService::class)->refund($topup, null, 'x');

        $this->assertSame(PaymentRefund::STATUS_DONE, $refund->status);
        $this->assertSame('RF_PP', $refund->provider_refund_ref);
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_a_card_refund_without_a_captured_charge_id_is_refused(): void
    {
        // No PaymentCharge row → the gateway has nothing to refund against.
        config(['services.stripe.secret_key' => 'sk_test_x']);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'stripe', 'ST-NOID', 10.0);

        $this->expectException(RefundException::class);
        app(RefundService::class)->refund($topup, null, 'x');
    }

    public function test_a_crypto_top_up_is_flagged_for_a_manual_refund_and_alerts(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'nowpayments', 'NP-REF-1', 15.0); // no RefundableGateway

        $refund = app(RefundService::class)->refund($topup, null, 'chargeback risk');

        $this->assertSame(PaymentRefund::STATUS_MANUAL, $refund->status);
        $this->assertSame('15.0000', (string) $user->wallet->fresh()->usd_balance); // NOT auto-moved
        Queue::assertPushed(AlertAdminJob::class);
    }
}
