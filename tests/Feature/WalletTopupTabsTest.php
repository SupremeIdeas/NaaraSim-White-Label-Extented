<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** Wallet > Top up: the Fiat and Crypto tabs each list only their own gateways, and the crypto tab carries the wallet-connect widget. */
class WalletTopupTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.paystack.secret_key' => 'sk_test', 'services.nowpayments.api_key' => 'np_test']);
    }

    public function test_fiat_tab_lists_only_fiat_gateways_and_crypto_tab_only_crypto_ones(): void
    {
        $c = Livewire::actingAs(User::factory()->create())->test(Wallet::class);
        $c->assertSet('method', 'fiat')->assertSee('data-topup-tab="crypto"', false)->assertSee('Paystack')->assertDontSee('NOWPayments');
        $c->call('setMethod', 'crypto')->assertSet('method', 'crypto')->assertSet('gateway', 'nowpayments')->assertSee('NOWPayments')->assertSee('More ways to pay with crypto');
        $c->call('setMethod', 'fiat')->assertSet('gateway', 'paystack')->assertDontSee('More ways to pay with crypto');
    }

    public function test_an_unknown_method_falls_back_to_fiat(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Wallet::class)->call('setMethod', 'bogus')->assertSet('method', 'fiat');
    }
}
