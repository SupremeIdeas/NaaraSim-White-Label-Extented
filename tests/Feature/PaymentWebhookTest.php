<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** Post a raw JSON body so signature bytes match exactly. */
    private function postRaw(string $uri, array $payload, array $headers = []): TestResponse
    {
        $content = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server, $content);
    }

    private function paystackPayload(User $user, string $ref = 'NAARA-PS-1'): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'reference' => $ref,
                'status' => 'success',
                'amount' => 500000, // kobo -> 5000.00
                'currency' => 'NGN',
                'metadata' => ['user_id' => $user->id],
            ],
        ];
    }

    private function paystackSign(array $payload): string
    {
        return hash_hmac('sha512', json_encode($payload), (string) config('services.paystack.secret_key'));
    }

    /** Drain the database queue deterministically (ignore a stale restart signal). */
    private function drainQueue(): void
    {
        Cache::forget('illuminate:queue:restart');
        $this->artisan('queue:work', ['--stop-when-empty' => true]);
    }

    public function test_paystack_valid_signature_credits_the_wallet_once_even_if_delivered_twice(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        $user = User::factory()->create();
        $payload = $this->paystackPayload($user);
        $sig = $this->paystackSign($payload);

        // Deliver twice (webhook retries / at-least-once delivery).
        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $sig])->assertOk();
        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $sig])->assertOk();

        // Unified USD Wallet (Part B): a raw NGN top-up (no TopUpIntent) now
        // converts to USD at the live rate rather than crediting ngn_balance.
        $this->assertSame('3.3300', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(1, WalletTransaction::where('reference', 'topup:paystack:NAARA-PS-1')->count());
    }

    public function test_paystack_credit_is_queued_not_inline_and_drains_to_a_single_credit(): void
    {
        // BUILD-2 §2/§9: the credit MUST run as a queued job (so a slow credit
        // never blocks the webhook 200), and the whole path must be exactly-once
        // even across a retry. Force the real `database` queue (not sync) and
        // prove: webhook 200 → job parked in the `jobs` table, wallet NOT yet
        // credited → worker drains → wallet credited once, ledger row written →
        // a retried delivery adds no second credit.
        config(['services.paystack.secret_key' => 'sk_test_secret', 'queue.default' => 'database']);
        $user = User::factory()->create();
        $payload = $this->paystackPayload($user);
        $sig = $this->paystackSign($payload);

        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $sig])->assertOk();

        // Queued, not run inline: a job is parked and nothing is credited yet.
        $this->assertSame(1, DB::table('jobs')->count(), 'credit must be queued, not inline');
        $this->assertNull($user->wallet, 'wallet must not be credited until the worker runs');

        // Drain the queue (the scheduled `queue:work --stop-when-empty` path).
        // Clear any stale queue:restart signal a prior test may have broadcast
        // (it makes the worker exit 12 to restart); we assert the CREDIT landed,
        // which is the real proof, not the worker's exit code.
        $this->drainQueue();

        // Credit landed: ledger row written, jobs table emptied.
        $this->assertSame(1, WalletTransaction::where('reference', 'topup:paystack:NAARA-PS-1')->count());
        $this->assertSame(0, DB::table('jobs')->count());
        // Unified USD Wallet (Part B): a raw NGN top-up (no TopUpIntent) now
        // converts to USD at the live rate rather than crediting ngn_balance.
        $this->assertSame('3.3300', (string) $user->fresh()->wallet->usd_balance);

        // A retried delivery after the first credit adds no second credit.
        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $sig])->assertOk();
        $this->drainQueue();
        $this->assertSame(1, WalletTransaction::where('reference', 'topup:paystack:NAARA-PS-1')->count());
        $this->assertSame('3.3300', (string) $user->fresh()->wallet->usd_balance);
    }

    public function test_paystack_invalid_signature_is_rejected_and_nothing_is_credited(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        $user = User::factory()->create();

        $this->postRaw('/webhooks/payments/paystack', $this->paystackPayload($user), [
            'x-paystack-signature' => 'deadbeef',
        ])->assertStatus(401);

        $this->assertNull($user->wallet); // no wallet ever created
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'paystack', 'verified' => false]);
    }

    public function test_flutterwave_verif_hash_credits_on_match(): void
    {
        config(['services.flutterwave.secret_hash' => 'flw-secret-hash']);
        $user = User::factory()->create();
        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => 'NAARA-FLW-1', 'status' => 'successful',
                'amount' => 12.5, 'currency' => 'USD', 'meta' => ['user_id' => $user->id],
            ],
        ];

        $this->postRaw('/webhooks/payments/flutterwave', $payload, ['verif-hash' => 'flw-secret-hash'])->assertOk();
        $this->assertSame('12.5000', (string) $user->wallet->fresh()->usd_balance);

        // Wrong hash rejected.
        $other = User::factory()->create();
        $this->postRaw('/webhooks/payments/flutterwave', [
            'event' => 'charge.completed',
            'data' => ['tx_ref' => 'X', 'status' => 'successful', 'amount' => 5, 'currency' => 'USD', 'meta' => ['user_id' => $other->id]],
        ], ['verif-hash' => 'wrong'])->assertStatus(401);
        $this->assertNull($other->wallet);
    }

    public function test_stripe_signature_scheme_is_verified_and_credits(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $user = User::factory()->create();
        $payload = [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_1', 'client_reference_id' => 'NAARA-ST-1', 'payment_status' => 'paid',
                'amount_total' => 2500, 'currency' => 'usd', 'metadata' => ['user_id' => $user->id],
            ]],
        ];
        $content = json_encode($payload);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$content}", 'whsec_test');

        $this->postRaw('/webhooks/payments/stripe', $payload, ['Stripe-Signature' => "t={$t},v1={$sig}"])->assertOk();
        $this->assertSame('25.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_stripe_rejects_a_stale_timestamp(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $user = User::factory()->create();
        $payload = ['type' => 'checkout.session.completed', 'data' => ['object' => [
            'client_reference_id' => 'OLD', 'payment_status' => 'paid', 'amount_total' => 2500,
            'currency' => 'usd', 'metadata' => ['user_id' => $user->id],
        ]]];
        $content = json_encode($payload);
        $t = time() - 999; // outside tolerance
        $sig = hash_hmac('sha256', "{$t}.{$content}", 'whsec_test');

        $this->postRaw('/webhooks/payments/stripe', $payload, ['Stripe-Signature' => "t={$t},v1={$sig}"])->assertStatus(401);
        $this->assertNull($user->wallet);
    }

    public function test_paystack_with_an_unconfigured_secret_cannot_be_forged(): void
    {
        // Secret unset (empty). An attacker computes hash_hmac(body, '') — which
        // anyone can, since the "key" is blank — and sends it as the signature.
        // Without the empty-secret guard this would validate and credit the
        // attacker's own wallet for free. It must be rejected 401 instead.
        config(['services.paystack.secret_key' => '']);
        $user = User::factory()->create();
        $payload = $this->paystackPayload($user);
        $forged = hash_hmac('sha512', json_encode($payload), '');

        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $forged])
            ->assertStatus(401);

        $this->assertNull($user->wallet);
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'paystack', 'verified' => false]);
    }

    public function test_unknown_gateway_is_404(): void
    {
        $this->postRaw('/webhooks/payments/bitcoin', [])->assertNotFound();
    }
}
