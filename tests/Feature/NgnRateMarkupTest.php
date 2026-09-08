<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Pricing\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The USD→NGN rate must reflect the parallel ("black-market") rate Nigerian
 * customers actually transact at. A manual rate is used as-is; an admin markup
 * lifts the base rate to bridge the official→parallel gap, and the same rate is
 * used for display and payouts so they never disagree.
 */
class NgnRateMarkupTest extends TestCase
{
    use RefreshDatabase;

    private function rate(): float
    {
        $svc = app(CurrencyService::class);
        $svc->flushNgnRate();

        return $svc->getUsdToNgn();
    }

    public function test_manual_rate_is_used_as_typed_with_no_markup(): void
    {
        Setting::setValue('pricing.ngn_rate_source', 'manual', 'pricing');
        Setting::setValue('pricing.manual_ngn_rate', 1650, 'pricing');
        Setting::setValue('pricing.ngn_rate_markup_pct', 0, 'pricing');

        $this->assertSame(1650.0, $this->rate());
    }

    public function test_markup_lifts_the_base_rate_toward_the_parallel_rate(): void
    {
        Setting::setValue('pricing.ngn_rate_source', 'manual', 'pricing');
        Setting::setValue('pricing.manual_ngn_rate', 1500, 'pricing');
        Setting::setValue('pricing.ngn_rate_markup_pct', 10, 'pricing');

        // 1500 + 10% = 1650.
        $this->assertSame(1650.0, $this->rate());
    }

    public function test_markup_is_clamped_to_50_percent(): void
    {
        Setting::setValue('pricing.ngn_rate_source', 'manual', 'pricing');
        Setting::setValue('pricing.manual_ngn_rate', 1000, 'pricing');
        Setting::setValue('pricing.ngn_rate_markup_pct', 999, 'pricing');

        // Clamped to +50% → 1500, never runaway.
        $this->assertSame(1500.0, $this->rate());
    }

    public function test_display_and_payout_use_the_same_rate(): void
    {
        Setting::setValue('pricing.ngn_rate_source', 'manual', 'pricing');
        Setting::setValue('pricing.manual_ngn_rate', 1600, 'pricing');
        Setting::setValue('pricing.ngn_rate_markup_pct', 5, 'pricing');

        $svc = app(CurrencyService::class);
        $svc->flushNgnRate();

        // rate('NGN') (display) reuses getUsdToNgn() (payout) — identical.
        $this->assertSame($svc->getUsdToNgn(), $svc->rate('NGN'));
        $this->assertSame(1680.0, $svc->rate('NGN')); // 1600 + 5%
    }
}
