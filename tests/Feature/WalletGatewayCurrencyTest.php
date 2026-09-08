<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\TopUpIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wallet.php's gateway/currency orchestration (Unified USD Wallet, Part B
 * §3.6) — a real dropdown of every currency a configured gateway accepts,
 * with "Pay with" reactively filtered so the UI never offers a pairing the
 * chosen gateway doesn't actually support.
 */
class WalletGatewayCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.paystack.secret_key' => 'sk_test_secret',
            'services.stripe.secret_key' => 'sk_test_stripe',
        ]);
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['GBP' => 0.80, 'EUR' => 0.92, 'USD' => 1, 'NGN' => 1600]]),
            'api.paystack.co/*' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'r']]),
            'api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/xyz']),
        ]);
    }

    public function test_mount_never_boots_into_an_unsupported_pairing(): void
    {
        // Only Stripe configured — it doesn't accept NGN (the component's
        // hardcoded default), so mount() must correct it to one Stripe does.
        config(['services.paystack.secret_key' => '']);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSet('gateway', 'stripe')
            ->assertSet('currency', 'USD');
    }

    public function test_switching_currency_drops_a_gateway_that_does_not_accept_it(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSet('gateway', 'paystack')
            ->set('currency', 'GBP') // Paystack doesn't accept GBP; Stripe does.
            ->assertSet('gateway', 'stripe');
    }

    public function test_switching_gateway_drops_a_currency_it_does_not_accept(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->set('currency', 'NGN')
            ->set('gateway', 'stripe') // Stripe doesn't accept NGN.
            ->assertSet('currency', 'USD');
    }

    public function test_server_side_blocks_a_currency_no_active_gateway_accepts(): void
    {
        // Only Paystack is configured, and it doesn't accept EUR — with no
        // active gateway to fall back to, updatedCurrency() leaves the now-
        // invalid pairing in place rather than guessing. topUp() must catch
        // this itself rather than trusting the UI ever prevented it.
        config(['services.stripe.secret_key' => '']);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSet('gateway', 'paystack')
            ->set('currency', 'EUR')
            ->assertSet('gateway', 'paystack') // left in place, not silently dropped
            ->set('amount', 20)
            ->call('topUp')
            ->assertSet('error', fn ($error) => str_contains($error, "doesn't accept EUR"));

        $this->assertSame(0, TopUpIntent::where('user_id', $user->id)->count());
    }
}
