<?php

namespace Tests\Feature;

use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Support\MerchantSettings;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ROADMAP §Layer 3.2 — the reseller price lane. Merchant price = retail + an
 * admin-set reseller margin, stacked ABOVE retail so the admin's profit is always
 * kept and the merchant earns the margin. MarginGuard still floors it.
 */
class MerchantPricingTest extends TestCase
{
    use RefreshDatabase;

    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue(MerchantSettings::MARGIN, 10.0, 'merchants');
        $this->engine = app(PricingEngine::class);
    }

    private function plan(array $attrs = []): EsimPlan
    {
        static $n = 0;
        $n++;

        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'MPLAN'.$n,
            'name' => 'Merchant Plan '.$n, 'cost_price_usd' => 10.0,
        ], $attrs));
    }

    public function test_merchant_price_stacks_the_reseller_margin_over_retail(): void
    {
        $plan = $this->plan(); // cost 10 → retail 13 (30%)
        $retail = $this->engine->calculateRetail($plan, log: false);
        $merchant = $this->engine->merchantEsimPrice($plan, log: false);

        // retail 13 * (1 + 10/100) = 14.30
        $this->assertSame(14.30, $merchant);
        $this->assertGreaterThan($retail, $merchant); // always above retail
    }

    public function test_admin_profit_is_untouched_and_merchant_earns_the_margin(): void
    {
        $plan = $this->plan();
        $cost = 10.0;
        $retail = $this->engine->calculateRetail($plan, log: false);
        $merchantPrice = $this->engine->merchantEsimPrice($plan, log: false);

        $adminProfit = round($retail - $cost, 2);              // R − C, always kept
        $merchantEarning = round($merchantPrice - $retail, 2); // M − R

        $this->assertSame(3.0, $adminProfit);
        $this->assertSame(1.30, $merchantEarning);
    }

    public function test_a_per_merchant_override_beats_the_global_margin(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'owner_user_id' => $user->id, 'business_name' => 'Acme', 'slug' => 'acme',
            'status' => Merchant::ACTIVE, 'reseller_margin_pct' => 20.0,
        ]);
        $plan = $this->plan();

        // retail 13 * (1 + 20/100) = 15.60
        $this->assertSame(15.60, $this->engine->merchantEsimPrice($plan, $merchant, log: false));
    }

    public function test_margin_guard_still_floors_a_zero_margin_merchant_price(): void
    {
        Setting::setValue(MerchantSettings::MARGIN, 0.0, 'merchants');
        $plan = $this->plan();

        $price = $this->engine->merchantEsimPrice($plan, log: false);
        // Even at 0 reseller margin, it's retail (13) which is already above the floor.
        $this->assertGreaterThanOrEqual(10.0 + 0.50, $price);
    }
}
