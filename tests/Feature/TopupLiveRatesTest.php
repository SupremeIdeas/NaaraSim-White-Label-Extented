<?php

namespace Tests\Feature;

use App\Services\Topup\DTO\ProductDTO;
use App\Services\Topup\TopupLiveRates;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeTopupProvider;
use Tests\TestCase;

class TopupLiveRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_supplier_is_asked_live_and_margin_and_cheapest_are_computed(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Cache::flush();
        $a = new FakeTopupProvider('zendit');
        $a->products = [new ProductDTO('A1', 'NG', 'MTN', 'airtime', 'Airtime', 'NGN', fixedAmount: 1000, costNative: 0.60, costCurrency: 'USD')];
        $b = new FakeTopupProvider('reloadly');
        $b->products = [new ProductDTO('B1', 'NG', 'MTN Nigeria', 'airtime', 'Airtime', 'NGN', fixedAmount: 1000, costNative: 0.70, costCurrency: 'USD')];
        $this->app->instance('topup.provider.zendit', $a);
        $this->app->instance('topup.provider.reloadly', $b);
        $this->app->instance('topup.provider.vtpass', new FakeTopupProvider('vtpass', configured: false));

        $r = app(TopupLiveRates::class)->run('NG', 'airtime', [1000], force: true);

        $this->assertSame('ok', $r['providers']['zendit']['state']);
        $this->assertSame('ok', $r['providers']['reloadly']['state']);
        $this->assertSame('off', $r['providers']['vtpass']['state']);
        $this->assertCount(2, $r['rows']);
        $byProvider = collect($r['rows'])->keyBy('provider');
        $this->assertTrue($byProvider['zendit']['cheapest']);
        $this->assertFalse($byProvider['reloadly']['cheapest']);
        $this->assertGreaterThan(0, $byProvider['zendit']['margin_usd']);
        $this->assertEqualsWithDelta(16.67, $byProvider['reloadly']['gap_to_best_pct'], 0.1);
        $this->assertGreaterThanOrEqual($byProvider['zendit']['cost_usd'], $byProvider['zendit']['price_usd']);
    }

    public function test_a_supplier_that_errors_is_reported_not_fatal(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Cache::flush();
        $bad = new class('zendit') extends FakeTopupProvider {
            public function listProducts(string $countryIso): array
            {
                throw new \RuntimeException('boom');
            }
        };
        $this->app->instance('topup.provider.zendit', $bad);
        $r = app(TopupLiveRates::class)->run('NG', 'airtime', [1000], force: true);
        $this->assertSame('error', $r['providers']['zendit']['state']);
        $this->assertSame('boom', $r['providers']['zendit']['error']);
    }
}
