<?php

namespace Tests\Feature;

use App\Models\EsimPlan;
use App\Models\PricingEngineLog;
use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Developer API price lane (ROADMAP §Layer 2). Developers resell at
 * wholesale + a small admin markup — a real discount vs retail, but MarginGuard
 * still guarantees the admin never sells at/below cost + minimum profit. This
 * locks that money-safety keystone before any API endpoints are built.
 */
class DeveloperPricingTest extends TestCase
{
    use RefreshDatabase;

    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        $this->engine = app(PricingEngine::class);
    }

    private function plan(array $attrs = []): EsimPlan
    {
        static $n = 0;
        $n++;

        return EsimPlan::create(array_merge([
            'provider' => 'esimgo',
            'provider_plan_id' => 'DEVPLAN'.$n,
            'name' => 'Dev Plan '.$n,
            'cost_price_usd' => 10.0,
        ], $attrs));
    }

    public function test_developer_esim_price_is_wholesale_plus_the_dev_markup(): void
    {
        // cost 10 * (1 + 10/100) = 11.00
        $plan = $this->plan();
        $this->assertSame(11.0, $this->engine->developerEsimPrice($plan));
    }

    public function test_developer_esim_price_is_below_retail_but_above_the_floor(): void
    {
        $plan = $this->plan(['cost_price_usd' => 10.0]);
        $retail = $this->engine->calculateRetail($plan, log: false); // 13.00 (30%)
        $dev = $this->engine->developerEsimPrice($plan, log: false);  // 11.00 (10%)

        $this->assertLessThan($retail, $dev);                 // a real developer discount
        $this->assertGreaterThan(10.0, $dev);                 // still above cost
        $this->assertGreaterThanOrEqual(10.0 + 0.50, $dev);   // above MarginGuard floor
    }

    public function test_margin_guard_floors_a_too_low_developer_markup(): void
    {
        // Admin (or Claude) sets a reckless 0% developer markup → dev == cost.
        Setting::setValue('pricing.developer_markup_pct', 0, 'pricing');
        $plan = $this->plan(['cost_price_usd' => 10.0]);

        // Clamped UP to cost + minimum_profit_usd (10 + 0.50).
        $this->assertSame(10.5, $this->engine->developerEsimPrice($plan));
    }

    public function test_developer_sms_price_applies_markup_and_is_margin_guarded(): void
    {
        // cost 0.20 * (1 + 15/100) = 0.23
        $this->assertSame(0.23, $this->engine->developerSmsPrice(0.20, 'fivesim'));

        // A thin cost where the markup can't clear the per-SMS floor gets clamped:
        // cost 0.01 * 1.15 = 0.0115, floor = cost + 0.01 = 0.02 → 0.02.
        $this->assertSame(0.02, $this->engine->developerSmsPrice(0.01, 'fivesim'));
    }

    public function test_developer_calculations_are_audited_under_a_dev_lane_marker(): void
    {
        $plan = $this->plan();
        $this->engine->developerEsimPrice($plan);
        $this->engine->developerSmsPrice(0.20, 'fivesim');

        // Logged for audit, and clearly tagged as the developer lane so admin
        // reporting never confuses it with retail.
        $this->assertTrue(PricingEngineLog::where('provider', 'dev:esimgo')->exists());
        $this->assertTrue(PricingEngineLog::where('provider', 'dev:fivesim')->exists());
    }
}
