<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stripe Connect account.updated webhook (ROADMAP §Layer 0.2). Its own
 * endpoint/secret, separate from the payments and payout-transfer webhooks —
 * verified before the payload is trusted, exactly like every other webhook.
 */
class StripeConnectWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function postRaw(array $payload, ?string $signature): TestResponse
    {
        $content = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_STRIPE_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/webhooks/stripe-connect/account', [], [], [], $server, $content);
    }

    private function sign(array $payload, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $sig = hash_hmac('sha256', "{$t}.".json_encode($payload), $secret);

        return "t={$t},v1={$sig}";
    }

    public function test_a_verified_account_updated_event_syncs_the_payout_account(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect']);
        $user = User::factory()->create();
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_1',
            'account_name' => 'acct_1', 'provider' => 'stripe', 'is_verified' => false, 'is_default' => true,
        ]);
        $payload = [
            'type' => 'account.updated',
            'data' => ['object' => [
                'id' => 'acct_1', 'details_submitted' => true, 'charges_enabled' => true, 'payouts_enabled' => true,
            ]],
        ];

        $this->postRaw($payload, $this->sign($payload, 'whsec_connect'))->assertOk();

        $account->refresh();
        $this->assertTrue($account->payouts_enabled);
        $this->assertTrue($account->is_verified);
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'stripe-connect', 'verified' => true]);
    }

    public function test_a_bad_signature_is_rejected_and_nothing_changes(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect']);
        $user = User::factory()->create();
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_2',
            'account_name' => 'acct_2', 'provider' => 'stripe', 'is_verified' => false, 'is_default' => true,
        ]);
        $payload = ['type' => 'account.updated', 'data' => ['object' => ['id' => 'acct_2', 'payouts_enabled' => true]]];

        $this->postRaw($payload, 't='.time().',v1=forged')->assertStatus(401);

        $this->assertFalse($account->fresh()->payouts_enabled);
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'stripe-connect', 'verified' => false]);
    }

    public function test_an_unrecognized_account_id_is_a_safe_no_op(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect']);
        $payload = ['type' => 'account.updated', 'data' => ['object' => ['id' => 'acct_unknown', 'payouts_enabled' => true]]];

        $this->postRaw($payload, $this->sign($payload, 'whsec_connect'))->assertOk();

        $this->assertDatabaseCount('payout_accounts', 0);
    }
}
