<?php

namespace Tests\Feature;

use App\Livewire\MerchantClients;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\Setting;
use App\Models\User;
use App\Services\Merchants\MerchantClientService;
use App\Services\Merchants\MerchantException;
use App\Services\Wallet\WalletService;
use App\Support\MerchantSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * Merchant V2 premium client eSIM control — the money-critical paths: device
 * compatibility gate, the locked auto-renewal reservation, the due-date
 * settlement (re-provision), refund-on-failure, disable, and expiry.
 */
class MerchantClientEsimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue(MerchantSettings::FLAG, true);
    }

    private function merchant(float $fund = 200): Merchant
    {
        $owner = User::factory()->create(['is_active' => true]);
        app(WalletService::class)->credit($owner, $fund, 'USD', ['reference' => 'seed:'.$owner->id]);

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Kedu Telecom', 'slug' => 'kedu-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_V2, 'reseller_margin_pct' => 10,
        ]);
    }

    private function plan(bool $voice = false): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => $voice ? 'Global Talk' : 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'], 'has_voice' => $voice,
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00, 'is_active' => true,
        ])->fresh();
    }

    private function fakeProvider(bool $throw = false): void
    {
        app()->instance('esim.esimgo', new FakeEsimProvider(shouldThrow: $throw, orderResponse: ['iccid' => '8944', 'orderReference' => 'O'.uniqid()]));
    }

    private function client(Merchant $m, array $attrs = []): MerchantClient
    {
        return $m->clients()->create(array_merge(['name' => 'Ada', 'is_active' => true, 'device' => 'iPhone 15'], $attrs));
    }

    public function test_assign_creates_a_subscription_with_type_and_expiry(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);

        app(MerchantClientService::class)->assignEsim($m, $client, $this->plan());

        $sub = $client->fresh()->activeSubscription();
        $this->assertNotNull($sub);
        $this->assertSame('data', $sub->esim_type);
        $this->assertSame('active', $sub->status);
        $this->assertNotNull($sub->expires_at);
        $this->assertGreaterThan(0, (float) $sub->renewal_price);
    }

    public function test_incompatible_device_is_blocked_unless_forced(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m, ['device' => 'Tecno Spark', 'device_os' => '']);

        $this->expectException(MerchantException::class);
        app(MerchantClientService::class)->assignEsim($m, $client, $this->plan());
    }

    public function test_forced_assign_bypasses_the_compat_gate(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m, ['device' => 'Tecno Spark', 'device_os' => '']);

        $order = app(MerchantClientService::class)->assignEsim($m, $client, $this->plan(), force: true);
        $this->assertSame($client->id, $order->merchant_client_id);
    }

    public function test_enable_auto_renew_reserves_and_locks_funds(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $spendableBefore = $wallet->spendableUsd($m->owner->fresh());

        $svc->enableAutoRenew($m, $sub->fresh());

        $price = (float) $sub->renewal_price;
        $this->assertTrue($sub->fresh()->auto_renew);
        $this->assertSame(round($price, 4), $wallet->reservedUsd($m->owner->fresh()));
        $this->assertSame(round($spendableBefore - $price, 4), $wallet->spendableUsd($m->owner->fresh()));
    }

    public function test_due_renewal_settles_and_reprovisions(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $svc->enableAutoRenew($m, $sub->fresh());
        $sub->update(['expires_at' => now()->subDay()]);

        $balanceBefore = (float) $m->owner->wallet->fresh()->usd_balance;
        $ok = $svc->renewDueSubscription($sub->fresh());

        $this->assertTrue($ok);
        $this->assertSame(0.0, $wallet->reservedUsd($m->owner->fresh()));                     // earmark freed
        $this->assertSame('expired', $sub->fresh()->status);                                  // old retired
        $this->assertLessThan($balanceBefore, (float) $m->owner->wallet->fresh()->usd_balance); // renewal charged
        // A fresh active subscription now exists for the client.
        $this->assertSame('active', $client->fresh()->activeSubscription()->status);
    }

    public function test_failed_renewal_returns_the_funds(): void
    {
        $this->fakeProvider();          // first assign succeeds
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $svc->enableAutoRenew($m, $sub->fresh());
        $sub->update(['expires_at' => now()->subDay()]);

        $balanceBefore = (float) $m->owner->wallet->fresh()->usd_balance;
        $this->fakeProvider(throw: true); // renewal provisioning fails
        $ok = $svc->renewDueSubscription($sub->fresh());

        $this->assertFalse($ok);
        $this->assertSame(0.0, $wallet->reservedUsd($m->owner->fresh()));  // earmark freed
        // Money fully returned: released earmark + provider self-refund.
        $this->assertSame(round($balanceBefore, 4), round((float) $m->owner->wallet->fresh()->usd_balance, 4));
    }

    public function test_multi_cycle_reserve_locks_every_cycle_up_front(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $price = (float) $sub->renewal_price;

        $svc->enableAutoRenew($m, $sub->fresh(), 4);

        $sub = $sub->fresh();
        $this->assertTrue($sub->auto_renew);
        $this->assertSame(4, $sub->reserved_cycles);
        $this->assertFalse($sub->renew_indefinitely);
        $this->assertSame(round(4 * $price, 4), $wallet->reservedUsd($m->owner->fresh()));
    }

    public function test_multi_cycle_renewal_carries_remaining_cycles_forward(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $price = (float) $sub->renewal_price;
        $svc->enableAutoRenew($m, $sub->fresh(), 3);
        $sub->update(['expires_at' => now()->subDay()]);

        $this->assertTrue($svc->renewDueSubscription($sub->fresh()));

        // Old row retired; a fresh active row carries the 2 remaining cycles,
        // still earmarked (no fresh reserve — the block covered them already).
        $fresh = $client->fresh()->activeSubscription();
        $this->assertSame('active', $fresh->status);
        $this->assertTrue($fresh->auto_renew);
        $this->assertSame(2, $fresh->reserved_cycles);
        $this->assertSame(round(2 * $price, 4), $wallet->reservedUsd($m->owner->fresh()));
    }

    public function test_last_reserved_cycle_renews_then_stops_auto_renew(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $svc->enableAutoRenew($m, $sub->fresh(), 1);
        $sub->update(['expires_at' => now()->subDay()]);

        $this->assertTrue($svc->renewDueSubscription($sub->fresh()));

        $fresh = $client->fresh()->activeSubscription();
        $this->assertSame('active', $fresh->status);
        $this->assertFalse($fresh->auto_renew);              // block exhausted
        $this->assertSame(0, $fresh->reserved_cycles);
        $this->assertSame(0.0, $wallet->reservedUsd($m->owner->fresh()));
    }

    public function test_indefinite_renewal_rolls_the_earmark_forward(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $price = (float) $sub->renewal_price;
        $svc->enableAutoRenew($m, $sub->fresh(), 1, indefinite: true);

        $this->assertSame(round($price, 4), $wallet->reservedUsd($m->owner->fresh()));
        $sub->update(['expires_at' => now()->subDay()]);

        $this->assertTrue($svc->renewDueSubscription($sub->fresh()));

        // Renewed AND re-armed: a single cycle stays reserved, indefinitely.
        $fresh = $client->fresh()->activeSubscription();
        $this->assertTrue($fresh->auto_renew);
        $this->assertTrue($fresh->renew_indefinitely);
        $this->assertSame(1, $fresh->reserved_cycles);
        $this->assertSame(round($price, 4), $wallet->reservedUsd($m->owner->fresh()));
    }

    public function test_disable_releases_all_reserved_cycles(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $price = (float) $sub->renewal_price;
        $svc->enableAutoRenew($m, $sub->fresh(), 5);
        $this->assertSame(round(5 * $price, 4), $wallet->reservedUsd($m->owner->fresh()));

        $svc->disableEsim($m, $sub->fresh());

        $this->assertSame('disabled', $sub->fresh()->status);
        $this->assertSame(0.0, $wallet->reservedUsd($m->owner->fresh()));  // every cycle freed
    }

    public function test_disable_releases_earmark_and_marks_disabled(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        $svc = app(MerchantClientService::class);
        $wallet = app(WalletService::class);

        $svc->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $svc->enableAutoRenew($m, $sub->fresh());
        $this->assertGreaterThan(0, $wallet->reservedUsd($m->owner->fresh()));

        $svc->disableEsim($m, $sub->fresh());

        $this->assertSame('disabled', $sub->fresh()->status);
        $this->assertSame(0.0, $wallet->reservedUsd($m->owner->fresh()));
    }

    public function test_command_expires_a_lapsed_subscription(): void
    {
        $this->fakeProvider();
        $m = $this->merchant();
        $client = $this->client($m);
        app(MerchantClientService::class)->assignEsim($m, $client, $this->plan());
        $sub = $client->fresh()->activeSubscription();
        $sub->update(['expires_at' => now()->subDays(2), 'auto_renew' => false]);

        $this->artisan('merchant:client-subscriptions')->assertExitCode(0);

        $this->assertSame('expired', $sub->fresh()->status);
    }

    public function test_invoice_generation_builds_a_whatsapp_link(): void
    {
        $m = $this->merchant();
        $client = $this->client($m, ['whatsapp' => '+2348012345678']);

        Livewire::actingAs($m->owner)->test(MerchantClients::class)
            ->call('openInvoice', $client->id)
            ->set('invoiceDesc', '1-month renewal')->set('invoiceAmount', 15)
            ->call('generateInvoice')
            ->assertSet('invoiceLink', fn ($v) => str_contains((string) $v, 'wa.me/2348012345678'))
            ->assertSee('Kedu Telecom');
    }
}
