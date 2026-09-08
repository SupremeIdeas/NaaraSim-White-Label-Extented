<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\StripePayoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stripe Connect payout sending (ROADMAP §Layer 0.2). A Transfer moving money
 * into the connected account's Stripe balance is the settlement event for our
 * own ledger — what Stripe does with it afterward (paying the connected
 * account's own bank) is between them and Stripe.
 */
class StripePayoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function gateway(): StripePayoutGateway
    {
        config(['services.stripe.secret_key' => 'sk_test_stripe']);

        return new StripePayoutGateway;
    }

    private function account(User $user): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_123',
            'account_name' => 'acct_123', 'provider' => 'stripe', 'provider_recipient_ref' => 'acct_123',
            'is_verified' => true, 'is_default' => true, 'payouts_enabled' => true,
        ]);
    }

    public function test_available_reflects_configured_secret_key(): void
    {
        config(['services.stripe.secret_key' => '']);
        $this->assertFalse((new StripePayoutGateway)->available());

        config(['services.stripe.secret_key' => 'sk_test_stripe']);
        $this->assertTrue((new StripePayoutGateway)->available());
    }

    public function test_create_recipient_is_just_the_connected_account_id(): void
    {
        $user = User::factory()->create();
        $this->assertSame('acct_123', $this->gateway()->createRecipient($this->account($user)));
    }

    public function test_a_successful_transfer_settles_immediately(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'tr_1'])]);
        $user = User::factory()->create();
        $account = $this->account($user);
        $request = PayoutRequest::create([
            'user_id' => $user->id, 'payout_account_id' => $account->id, 'amount' => 25.0,
            'currency' => 'USD', 'source_bucket' => 'referral_credits', 'status' => PayoutRequest::APPROVED,
            'provider' => 'stripe', 'reference' => 'wd:stripe-1',
        ]);

        $result = $this->gateway()->sendTransfer($request, $account);

        $this->assertSame('paid', $result->status);
        $this->assertSame('tr_1', $result->providerRef);
        Http::assertSent(fn ($req) => $req['destination'] === 'acct_123'
            && $req['amount'] === 2500
            && $req['currency'] === 'usd'
            && $req['metadata']['reference'] === 'wd:stripe-1');
    }

    public function test_a_rejected_transfer_fails_without_settling(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Insufficient platform balance.']], 402)]);
        $user = User::factory()->create();
        $account = $this->account($user);
        $request = PayoutRequest::create([
            'user_id' => $user->id, 'payout_account_id' => $account->id, 'amount' => 25.0,
            'currency' => 'USD', 'source_bucket' => 'referral_credits', 'status' => PayoutRequest::APPROVED,
            'provider' => 'stripe', 'reference' => 'wd:stripe-2',
        ]);

        $result = $this->gateway()->sendTransfer($request, $account);

        $this->assertSame('failed', $result->status);
        $this->assertSame('Insufficient platform balance.', $result->failureReason);
    }

    public function test_parse_webhook_only_reacts_to_transfer_reversed(): void
    {
        $gateway = $this->gateway();

        $ignored = Request::create('/webhooks/payouts/stripe', 'POST', [], [], [], [], json_encode([
            'type' => 'transfer.created', 'data' => ['object' => ['metadata' => ['reference' => 'wd:x']]],
        ]));
        $ignored->headers->set('content-type', 'application/json');
        $this->assertNull($gateway->parseWebhook($ignored));

        $reversed = Request::create('/webhooks/payouts/stripe', 'POST', [], [], [], [], json_encode([
            'type' => 'transfer.reversed', 'data' => ['object' => ['id' => 'trr_1', 'metadata' => ['reference' => 'wd:stripe-1']]],
        ]));
        $reversed->headers->set('content-type', 'application/json');
        $event = $gateway->parseWebhook($reversed);

        $this->assertNotNull($event);
        $this->assertSame('reversed', $event->status);
        $this->assertSame('wd:stripe-1', $event->reference);
        $this->assertSame('trr_1', $event->providerRef);
    }

    public function test_verify_webhook_checks_the_hmac_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $gateway = $this->gateway();
        $body = json_encode(['type' => 'transfer.reversed']);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", 'whsec_test');

        $good = Request::create('/webhooks/payouts/stripe', 'POST', [], [], [], [], $body);
        $good->headers->set('Stripe-Signature', "t={$t},v1={$sig}");
        $this->assertTrue($gateway->verifyWebhook($good));

        $bad = Request::create('/webhooks/payouts/stripe', 'POST', [], [], [], [], $body);
        $bad->headers->set('Stripe-Signature', "t={$t},v1=wrong");
        $this->assertFalse($gateway->verifyWebhook($bad));
    }
}
