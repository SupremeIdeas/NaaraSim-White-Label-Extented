<?php

namespace Tests\Feature;

use App\Jobs\RecomputePlanPricingJob;
use App\Models\EsimPlan;
use App\Models\PricingEngineLog;
use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PricingEngineTest extends TestCase
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
            'provider_plan_id' => 'PLAN'.$n,
            'name' => 'Test Plan '.$n,
            'cost_price_usd' => 10.0,
        ], $attrs));
    }

    public function test_basic_markup_formula(): void
    {
        // cost 10 * (1 + 30/100) = 13.00
        $this->assertSame(13.0, $this->engine->calculateRetail($this->plan()));
    }

    public function test_per_plan_override_markup_replaces_global(): void
    {
        // cost 10 * (1 + 50/100) = 15.00
        $plan = $this->plan(['override_markup_pct' => 50]);
        $this->assertSame(15.0, $this->engine->calculateRetail($plan));
    }

    public function test_margin_guard_floors_a_thin_margin_upward(): void
    {
        // cost 0.10, markup 30 -> 0.13, which is below cost + 0.50 floor (0.60).
        $plan = $this->plan(['cost_price_usd' => 0.10]);
        $retail = $this->engine->calculateRetail($plan);

        $this->assertSame(0.60, $retail);
        $log = PricingEngineLog::latest('id')->first();
        $this->assertSame('margin_guard', $log->guard_active);
        $this->assertGreaterThan(0, (float) $log->guard_delta);
    }

    public function test_retail_is_never_at_or_below_cost_plus_min_profit(): void
    {
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);

        // Property check across a wide spread of costs and markups (incl.
        // near-zero markups that would otherwise sell at cost).
        foreach ([0.01, 0.10, 0.99, 1.0, 5.0, 12.34, 100.0, 999.99] as $cost) {
            foreach ([0, 1, 5, 30, 200] as $markup) {
                $plan = $this->plan([
                    'cost_price_usd' => $cost,
                    'override_markup_pct' => $markup,
                ]);
                $retail = $this->engine->calculateRetail($plan);
                $this->assertGreaterThanOrEqual(
                    $cost + $minProfit,
                    $retail,
                    "retail {$retail} dipped to/below cost {$cost} + min profit {$minProfit}"
                );
            }
        }
    }

    public function test_airalo_minimum_selling_price_guard_corrects_up(): void
    {
        // cost 2, markup 30 -> 2.60, but Airalo min is 5.00 -> raised to 5.00.
        $plan = $this->plan([
            'provider' => 'airalo',
            'cost_price_usd' => 2.0,
            'override_markup_pct' => 30,
            'airalo_min_price' => 5.0,
        ]);

        $this->assertSame(5.0, $this->engine->calculateRetail($plan));
        $this->assertSame('airalo_min', PricingEngineLog::latest('id')->first()->guard_active);
    }

    public function test_airalo_guard_does_not_apply_to_non_airalo_providers(): void
    {
        // Same numbers but provider esimgo: the airalo_min column is ignored.
        $plan = $this->plan([
            'provider' => 'esimgo',
            'cost_price_usd' => 2.0,
            'override_markup_pct' => 30,
            'airalo_min_price' => 5.0,
        ]);
        // 2 * 1.30 = 2.60, above the 2.50 floor, so no guard.
        $this->assertSame(2.6, $this->engine->calculateRetail($plan));
        $this->assertSame('none', PricingEngineLog::latest('id')->first()->guard_active);
    }

    public function test_manual_fixed_price_bypasses_formula_but_margin_guard_still_applies(): void
    {
        // A healthy manual price is used verbatim.
        $healthy = $this->plan(['cost_price_usd' => 5.0, 'manual_retail_usd' => 25.0]);
        $this->assertSame(25.0, $this->engine->calculateRetail($healthy));

        // A manual price below the floor is corrected up by MarginGuard.
        $tooLow = $this->plan(['cost_price_usd' => 5.0, 'manual_retail_usd' => 0.01]);
        $this->assertSame(5.5, $this->engine->calculateRetail($tooLow));
        $this->assertSame('margin_guard', PricingEngineLog::latest('id')->first()->guard_active);
    }

    public function test_sms_retail_applies_provider_markup_and_min_profit(): void
    {
        // fivesim markup 55%: 0.20 * 1.55 = 0.31
        $this->assertSame(0.31, $this->engine->calculateSmsRetail(0.20, 'fivesim'));

        // Tiny cost -> min-profit floor (0.01) dominates.
        $this->assertSame(0.011, $this->engine->calculateSmsRetail(0.001, 'fivesim'));
    }

    public function test_profit_summary_shape_and_values(): void
    {
        $plan = $this->plan(['cost_price_usd' => 10.0, 'override_markup_pct' => 30]);
        $summary = $this->engine->getProfitSummary($plan);

        $this->assertEqualsCanonicalizing(
            ['cost_price', 'retail_price', 'profit_usd', 'profit_pct'],
            array_keys($summary)
        );
        $this->assertSame(13.0, $summary['retail_price']);
        $this->assertSame(3.0, $summary['profit_usd']);
        $this->assertSame(30.0, $summary['profit_pct']);
    }

    public function test_every_calculation_is_logged(): void
    {
        $this->assertSame(0, PricingEngineLog::count());

        $this->engine->calculateRetail($this->plan());
        $this->engine->calculateSmsRetail(0.5, 'getatext');
        $this->engine->getProfitSummary($this->plan()); // calls calculateRetail

        $this->assertSame(3, PricingEngineLog::count());
    }

    public function test_recompute_stores_engine_price_and_generated_final_follows(): void
    {
        $plan = $this->plan(['cost_price_usd' => 10.0, 'override_markup_pct' => 30]);
        $this->engine->recompute($plan);

        $this->assertSame('13.0000', $plan->fresh()->computed_retail_usd);
        // No manual override -> final_retail_usd = computed.
        $this->assertSame(13.0, (float) $plan->fresh()->final_retail_usd);
    }

    public function test_recompute_job_reprices_all_plans_after_markup_change(): void
    {
        $a = $this->plan(['cost_price_usd' => 10.0]);
        $b = $this->plan(['cost_price_usd' => 20.0]);

        // Admin raises the global markup to 50%.
        Setting::setValue('pricing.default_markup_pct', 50, 'pricing');

        (new RecomputePlanPricingJob)->handle($this->engine);

        $this->assertSame(15.0, (float) $a->fresh()->final_retail_usd); // 10 * 1.5
        $this->assertSame(30.0, (float) $b->fresh()->final_retail_usd); // 20 * 1.5
    }

    public function test_recompute_job_flushes_the_storefront_teaser_cache(): void
    {
        // Readiness-audit fix (2026-09-07): a global markup change repriced
        // every plan but left the storefront's cached "from $X" grid on the
        // pre-change prices indefinitely.
        Cache::forever('esim.nav.grid.v1:data', ['stale' => true]);
        $this->plan(['cost_price_usd' => 10.0]);
        Setting::setValue('pricing.default_markup_pct', 50, 'pricing');

        (new RecomputePlanPricingJob)->handle($this->engine);

        $this->assertFalse(Cache::has('esim.nav.grid.v1:data'));
    }
}
