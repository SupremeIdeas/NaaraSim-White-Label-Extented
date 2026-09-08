<?php

namespace Tests\Feature;

use App\Livewire\MerchantClients;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\User;
use App\Services\Merchants\MerchantClientService;
use App\Services\Merchants\MerchantException;
use App\Services\Merchants\MerchantUpgradeService;
use App\Services\Wallet\WalletService;
use App\Support\MerchantSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * Merchant V2 — the client-management upgrade tier. Covers both upgrade paths
 * ($125 self-pay from wallet + admin grant), the V2 gate on client management,
 * and that assigning an eSIM charges the MERCHANT at merchant price and tags the
 * order with the client (one-to-many history).
 */
class MerchantV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        \App\Models\Setting::setValue(MerchantSettings::FLAG, true);
    }

    private function merchant(string $tier = Merchant::TIER_STANDARD, float $fund = 0): Merchant
    {
        $owner = User::factory()->create();
        if ($fund > 0) {
            app(WalletService::class)->credit($owner, $fund, 'USD', ['reference' => 'seed:'.$owner->id]);
        }

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => 'active', 'tier' => $tier, 'reseller_margin_pct' => 10,
        ]);
    }

    public function test_self_upgrade_charges_the_wallet_and_flips_the_tier(): void
    {
        \App\Models\Setting::setValue(MerchantSettings::UPGRADE_PRICE, 125);
        $merchant = $this->merchant(fund: 200);

        app(MerchantUpgradeService::class)->selfUpgrade($merchant);

        $this->assertTrue($merchant->fresh()->isV2());
        $this->assertSame('75.0000', (string) $merchant->owner->wallet->fresh()->usd_balance); // 200 − 125
    }

    public function test_self_upgrade_needs_a_funded_wallet(): void
    {
        \App\Models\Setting::setValue(MerchantSettings::UPGRADE_PRICE, 125);
        $merchant = $this->merchant(fund: 10);

        $this->expectException(MerchantException::class);
        app(MerchantUpgradeService::class)->selfUpgrade($merchant);
    }

    public function test_admin_can_grant_the_upgrade_for_free(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $merchant = $this->merchant();

        app(MerchantUpgradeService::class)->grant($merchant, $admin);

        $this->assertTrue($merchant->fresh()->isV2());
    }

    public function test_client_management_is_v2_gated(): void
    {
        $standard = $this->merchant(Merchant::TIER_STANDARD);
        Livewire::actingAs($standard->owner)->test(MerchantClients::class)->assertStatus(404);

        $v2 = $this->merchant(Merchant::TIER_V2);
        Livewire::actingAs($v2->owner)->test(MerchantClients::class)->assertOk();
    }

    public function test_assigning_an_esim_charges_the_merchant_and_tags_the_order(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2, fund: 50);
        $client = $merchant->clients()->create(['name' => 'Ada', 'is_active' => true]);
        $plan = EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ])->fresh();
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        $before = (float) $merchant->owner->wallet->fresh()->usd_balance;
        $order = app(MerchantClientService::class)->assignEsim($merchant, $client, $plan);

        // Order is tagged with the client (one-to-many history) and owned by the merchant.
        $this->assertSame($client->id, $order->merchant_client_id);
        $this->assertSame($merchant->owner_user_id, $order->user_id);
        // The merchant wallet was charged the merchant price (> retail 10).
        $after = (float) $merchant->owner->wallet->fresh()->usd_balance;
        $this->assertGreaterThan(0, $before - $after);
        $this->assertSame(1, MerchantClient::find($client->id)->esimOrders()->count());
    }

    public function test_a_standard_merchant_cannot_manage_clients_via_the_service(): void
    {
        $standard = $this->merchant(Merchant::TIER_STANDARD);
        $this->expectException(MerchantException::class);
        app(MerchantClientService::class)->addClient($standard, ['name' => 'X']);
    }

    public function test_admin_can_grant_v2_and_set_the_upgrade_price(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $merchant = $this->merchant();

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\Merchants::class)
            ->set('upgradePrice', 150)->call('save')
            ->call('upgrade', $merchant->id)->assertHasNoErrors();

        $this->assertTrue($merchant->fresh()->isV2());
        $this->assertSame(150.0, MerchantSettings::upgradePriceUsd());
    }

    public function test_the_dashboard_self_upgrade_flips_the_tier(): void
    {
        \App\Models\Setting::setValue(MerchantSettings::UPGRADE_PRICE, 125);
        $merchant = $this->merchant(fund: 200);

        Livewire::actingAs($merchant->owner)->test(\App\Livewire\MerchantDashboard::class)
            ->call('upgradeToV2');

        $this->assertTrue($merchant->fresh()->isV2());
    }
}
