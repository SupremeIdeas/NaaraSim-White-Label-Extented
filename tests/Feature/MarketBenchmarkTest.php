<?php

namespace Tests\Feature;

use App\Models\EsimPlan;
use App\Services\Pricing\PricingArchitect;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Always-on market-competitiveness benchmark (owner request). Rates each data
 * plan's retail against a transparent, admin-tunable market model and gives a
 * verdict — competitive / keen / above market — with or without the Claude key.
 */
class MarketBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
    }

    private function plan(float $retail, int $mb = 1024, int $days = 30): EsimPlan
    {
        static $n = 0;
        $n++;

        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'MB'.$n, 'name' => 'Plan '.$n,
            'cost_price_usd' => 1.00, 'computed_retail_usd' => $retail,
            'data_mb' => $mb, 'validity_days' => $days, 'is_active' => true,
        ]);
    }

    public function test_it_positions_plans_against_the_market_band(): void
    {
        // Model: per_gb 2.50·1 + per_day 0.08·30 + base 0.99 = 5.89 mid; band ±15%
        // → roughly 5.01–6.77.
        $this->plan(retail: 5.89);  // competitive (mid)
        $this->plan(retail: 3.00);  // keen (below)
        $this->plan(retail: 9.00);  // premium (above)

        $market = app(PricingArchitect::class)->marketBenchmark();

        $this->assertSame(1, $market['competitive']);
        $this->assertSame(1, $market['keen']);
        $this->assertSame(1, $market['premium']);
        $this->assertNotEmpty($market['verdict']);

        $positions = collect($market['rows'])->pluck('position')->all();
        $this->assertContains('competitive', $positions);
        $this->assertContains('keen', $positions);
        $this->assertContains('premium', $positions);
    }

    public function test_rows_carry_the_band_and_delta_but_never_the_cost_to_users(): void
    {
        $this->plan(retail: 5.89);
        $row = app(PricingArchitect::class)->marketBenchmark()['rows'][0];

        $this->assertArrayHasKey('market_low', $row);
        $this->assertArrayHasKey('market_high', $row);
        $this->assertArrayHasKey('delta_pct', $row);
        $this->assertSame('competitive', $row['position']);
    }
}
