<?php

namespace Tests\Feature;

use App\Models\PaymentDispute;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BUILD-2 §7.2 — card disputes/chargebacks: freeze the disputed amount from the
 * wallet on open, release on win, release + debit on loss. Idempotent, and the
 * disputed funds are unspendable while contested.
 */
class PaymentDisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => 'sk_test_secret']);
    }

    private function postRaw(array $payload): TestResponse
    {
        $content = json_encode($payload);
        $sig = hash_hmac('sha512', $content, 'sk_test_secret');

        return $this->call('POST', '/webhooks/payments/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $sig,
        ], $content);
    }

    private function usdTopUp(User $user, string $ref, float $amount): void
    {
        app(WalletService::class)->credit($user, $amount, 'USD', [
            'reference' => "topup:paystack:{$ref}",
            'description' => 'Wallet top-up via paystack',
        ]);
    }

    private function openPayload(int $id, string $ref, float $usd): array
    {
        return ['event' => 'charge.dispute.create', 'data' => [
            'id' => $id,
            'transaction' => ['reference' => $ref, 'amount' => (int) round($usd * 100), 'currency' => 'USD'],
        ]];
    }

    private function resolvePayload(int $id, string $ref, string $resolution, float $usd): array
    {
        return ['event' => 'charge.dispute.resolve', 'data' => [
            'id' => $id,
            'resolution' => $resolution, // merchant-accepted = lost | declined = won
            'refund_amount' => $resolution === 'merchant-accepted' ? (int) round($usd * 100) : 0,
            'transaction' => ['reference' => $ref, 'amount' => (int) round($usd * 100), 'currency' => 'USD'],
        ]];
    }

    public function test_opening_a_dispute_freezes_the_funds_from_spending(): void
    {
        $user = User::factory()->create();
        $this->usdTopUp($user, 'D-1', 50.0);
        $wallet = app(WalletService::class);
        $this->assertSame(50.0, $wallet->spendableUsd($user->fresh()));

        $this->postRaw($this->openPayload(1001, 'D-1', 50.0))->assertOk();

        $dispute = PaymentDispute::where('provider_dispute_id', '1001')->first();
        $this->assertNotNull($dispute);
        $this->assertSame('open', $dispute->status);
        $this->assertSame('50.0000', (string) $dispute->frozen_amount);
        // Balance unchanged but spendable is now 0 — the money is frozen.
        $this->assertSame('50.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0.0, $wallet->spendableUsd($user->fresh()));
    }

    public function test_a_lost_dispute_releases_then_debits_the_frozen_amount(): void
    {
        $user = User::factory()->create();
        $this->usdTopUp($user, 'D-2', 40.0);

        $this->postRaw($this->openPayload(1002, 'D-2', 40.0))->assertOk();
        $this->postRaw($this->resolvePayload(1002, 'D-2', 'merchant-accepted', 40.0))->assertOk();

        $dispute = PaymentDispute::where('provider_dispute_id', '1002')->first();
        $this->assertSame('lost', $dispute->status);
        // Money pulled back: balance 0, nothing left reserved.
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0.0, app(WalletService::class)->reservedUsd($user->fresh()));
        $this->assertDatabaseHas('wallet_transactions', ['reference' => 'chargeback:paystack:1002', 'type' => 'debit']);
    }

    public function test_a_won_dispute_unfreezes_and_keeps_the_money(): void
    {
        $user = User::factory()->create();
        $this->usdTopUp($user, 'D-3', 30.0);

        $this->postRaw($this->openPayload(1003, 'D-3', 30.0))->assertOk();
        $this->postRaw($this->resolvePayload(1003, 'D-3', 'declined', 30.0))->assertOk();

        $dispute = PaymentDispute::where('provider_dispute_id', '1003')->first();
        $this->assertSame('won', $dispute->status);
        $wallet = app(WalletService::class);
        // Unfrozen and intact — spendable back to full, no chargeback debit.
        $this->assertSame('30.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(30.0, $wallet->spendableUsd($user->fresh()));
        $this->assertDatabaseMissing('wallet_transactions', ['reference' => 'chargeback:paystack:1003']);
    }

    public function test_a_duplicate_open_webhook_creates_one_dispute_and_freezes_once(): void
    {
        $user = User::factory()->create();
        $this->usdTopUp($user, 'D-4', 25.0);

        $this->postRaw($this->openPayload(1004, 'D-4', 25.0))->assertOk();
        $this->postRaw($this->openPayload(1004, 'D-4', 25.0))->assertOk();

        $this->assertSame(1, PaymentDispute::where('provider_dispute_id', '1004')->count());
        // Frozen once, not twice.
        $this->assertSame(25.0, app(WalletService::class)->reservedUsd($user->fresh()));
    }

    public function test_a_dispute_only_freezes_what_the_user_still_has(): void
    {
        $user = User::factory()->create();
        $this->usdTopUp($user, 'D-5', 20.0);
        // User already spent 15 of the 20.
        app(WalletService::class)->debit($user, 15.0, 'USD', ['reference' => 'spend:d5', 'description' => 'bought']);

        $this->postRaw($this->openPayload(1005, 'D-5', 20.0))->assertOk();

        $dispute = PaymentDispute::where('provider_dispute_id', '1005')->first();
        // Only the remaining 5 could be frozen; the 15 gap is the real exposure.
        $this->assertSame('5.0000', (string) $dispute->frozen_amount);
        $this->assertSame(0.0, app(WalletService::class)->spendableUsd($user->fresh()));
    }
}
