<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\TopUpIntent;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Deposit in local currency (owner request — money-safe half). A user paying in
 * their local currency has the USD wallet credit LOCKED at initiation on a
 * TopUpIntent; the webhook credits exactly that USD figure, never one re-derived
 * from the gateway's reported currency. USD/NGN deposits are unchanged.
 */
class DepositLocalCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        // Flutterwave also configured: Paystack doesn't accept GBP (Part B's
        // GatewayCurrencyMatrix), so a GBP top-up only offers gateways that
        // actually support it.
        config(['services.flutterwave.secret_key' => 'flw_test_secret']);
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['GBP' => 0.80, 'USD' => 1, 'NGN' => 1600]]),
            'api.paystack.co/*' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'r']]),
            'api.flutterwave.com/*' => Http::response(['data' => ['link' => 'https://checkout.flutterwave.com/xyz']]),
        ]);
    }

    private function postRaw(string $uri, array $payload, array $headers = []): TestResponse
    {
        $content = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $this->call('POST', $uri, [], [], [], $server, $content);
    }

    public function test_a_local_currency_topup_locks_the_usd_credit_on_an_intent(): void
    {
        $user = User::factory()->create(['display_currency' => 'GBP']);

        Livewire::actingAs($user)->test(Wallet::class)
            ->set('currency', 'GBP')
            ->set('amount', 20) // £20 at 0.80 → $25.00
            ->call('topUp');

        $intent = TopUpIntent::where('user_id', $user->id)->first();
        $this->assertNotNull($intent);
        $this->assertSame('GBP', $intent->charge_currency);
        $this->assertSame('20.0000', (string) $intent->charge_amount);
        $this->assertSame('25.0000', (string) $intent->usd_amount); // 20 / 0.80
        $this->assertSame('pending', $intent->status);
    }

    public function test_the_webhook_credits_the_locked_usd_not_the_reported_amount(): void
    {
        $user = User::factory()->create();
        // An intent locked at $25 USD for a £20 charge.
        TopUpIntent::create([
            'user_id' => $user->id, 'gateway' => 'paystack', 'reference' => 'NAARA-GBP-1',
            'charge_amount' => 20, 'charge_currency' => 'GBP', 'usd_amount' => 25.00,
            'rate_usd_to_local' => 0.80, 'status' => 'pending',
        ]);

        // Paystack reports the GBP charge (2000 pence) — but we must credit $25.
        $payload = [
            'event' => 'charge.success',
            'data' => ['reference' => 'NAARA-GBP-1', 'status' => 'success', 'amount' => 200000, 'currency' => 'GBP', 'metadata' => ['user_id' => $user->id]],
        ];
        $sig = hash_hmac('sha512', json_encode($payload), 'sk_test_secret');

        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $sig])->assertOk();

        // USD wallet credited the LOCKED $25.00 (not £2000 / not NGN).
        $this->assertSame('25.0000', (string) $user->wallet->fresh()->usd_balance);
        $txn = WalletTransaction::where('reference', 'topup:paystack:NAARA-GBP-1')->first();
        $this->assertSame('USD', $txn->currency);
        $this->assertSame('25.0000', (string) $txn->amount);
        $this->assertSame('credited', TopUpIntent::where('reference', 'NAARA-GBP-1')->first()->status);
    }

    public function test_a_usd_deposit_still_credits_directly_with_no_intent(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->set('currency', 'USD')
            ->set('amount', 20)
            ->call('topUp');

        $this->assertSame(0, TopUpIntent::where('user_id', $user->id)->count());
    }
}
