<?php

namespace Tests\Feature;

use App\Models\PaymentCharge;
use App\Models\PaymentDispute;
use App\Models\User;
use App\Services\Payments\DisputeEvent;
use App\Services\Payments\DisputeService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-2 §7.2 — Stripe/PayPal disputes cite the provider charge id (payment
 * intent / capture id), not our reference. The captured PaymentCharge maps it
 * back to the right user so the freeze/claw-back lands on the correct wallet.
 */
class DisputeMappingTest extends TestCase
{
    use RefreshDatabase;

    private function seedCharge(User $user, string $gateway, string $ref, string $chargeId, float $usd): void
    {
        app(WalletService::class)->credit($user, $usd, 'USD', [
            'reference' => "topup:{$gateway}:{$ref}",
            'description' => "Wallet top-up via {$gateway}",
        ]);
        PaymentCharge::create([
            'gateway' => $gateway, 'reference' => $ref, 'provider_charge_id' => $chargeId,
            'amount' => $usd, 'currency' => 'USD',
        ]);
    }

    public function test_a_stripe_dispute_maps_the_payment_intent_to_the_user_and_freezes(): void
    {
        $user = User::factory()->create();
        $this->seedCharge($user, 'stripe', 'ST-D1', 'pi_abc', 40.0);

        $event = new DisputeEvent(
            gateway: 'stripe', providerDisputeId: 'du_1', reference: null,
            amount: 40.0, currency: 'USD', status: DisputeEvent::OPEN, providerChargeId: 'pi_abc',
        );
        app(DisputeService::class)->handle($event);

        $dispute = PaymentDispute::where('provider_dispute_id', 'du_1')->first();
        $this->assertNotNull($dispute);
        $this->assertSame($user->id, $dispute->user_id);    // mapped via payment_intent
        $this->assertSame('ST-D1', $dispute->reference);
        $this->assertSame('40.0000', (string) $dispute->frozen_amount);
        $this->assertSame(0.0, app(WalletService::class)->spendableUsd($user->fresh()));
    }

    public function test_a_lost_stripe_dispute_debits_the_mapped_user(): void
    {
        $user = User::factory()->create();
        $this->seedCharge($user, 'stripe', 'ST-D2', 'pi_def', 25.0);

        app(DisputeService::class)->handle(new DisputeEvent('stripe', 'du_2', null, 25.0, 'USD', DisputeEvent::OPEN, [], 'pi_def'));
        app(DisputeService::class)->handle(new DisputeEvent('stripe', 'du_2', null, 25.0, 'USD', DisputeEvent::LOST, [], 'pi_def'));

        $this->assertSame('lost', PaymentDispute::where('provider_dispute_id', 'du_2')->first()->status);
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0.0, app(WalletService::class)->reservedUsd($user->fresh()));
    }

    public function test_a_paypal_dispute_maps_the_capture_id_to_the_user(): void
    {
        $user = User::factory()->create();
        $this->seedCharge($user, 'paypal', 'PP-D1', 'CAP-xyz', 15.0);

        app(DisputeService::class)->handle(new DisputeEvent('paypal', 'PP-DU-1', null, 15.0, 'USD', DisputeEvent::OPEN, [], 'CAP-xyz'));

        $dispute = PaymentDispute::where('provider_dispute_id', 'PP-DU-1')->first();
        $this->assertSame($user->id, $dispute->user_id);
        $this->assertSame('PP-D1', $dispute->reference);
    }
}
