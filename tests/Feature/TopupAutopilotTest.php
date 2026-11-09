<?php

namespace Tests\Feature;

use App\Jobs\Topup\TopupAutopilotJob;
use App\Models\Setting;
use App\Models\TopupProvider;
use App\Models\TopupRoute;
use App\Services\Topup\DTO\ProductDTO;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeTopupProvider;
use Tests\TestCase;

class TopupAutopilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_seeds_itself_even_with_no_supplier_keys(): void
    {
        $this->assertSame(0, TopupRoute::count());

        app(TopupAutopilotJob::class)->handle(...$this->deps());

        $this->assertGreaterThan(0, TopupRoute::count());
        $this->assertGreaterThan(0, TopupProvider::count());
        $this->assertNull(Setting::getValue('topup.last_sync_at'));      // nothing to sync until a supplier has keys
    }

    public function test_once_a_supplier_is_live_it_syncs_and_runs_the_daily_live_check_by_itself(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Cache::flush();
        $z = new FakeTopupProvider('zendit');
        $z->products = [new ProductDTO('A1', 'NG', 'MTN', 'airtime', 'Airtime', 'NGN', fixedAmount: 1000, costNative: 0.60, costCurrency: 'USD')];
        $this->app->instance('topup.provider.zendit', $z);
        foreach (['vtpass', 'reloadly', 'ding', 'bitrefill', 'paga'] as $c) {
            $this->app->instance('topup.provider.'.$c, new FakeTopupProvider($c, configured: false));
        }
        app(\App\Services\Topup\TopupOps::class)->seed();
        TopupProvider::where('code', 'zendit')->update(['enabled' => true]);

        app(TopupAutopilotJob::class)->handle(...$this->deps());

        $this->assertNotNull(Setting::getValue('topup.last_sync_at'));
        $last = Setting::getValue('topup.live_rates_last');
        $this->assertArrayHasKey('NG', $last['countries']);
        $this->assertSame('ok', $last['countries']['NG']['airtime']['providers']['zendit']);

        // a second run inside the window does nothing new
        $at = Setting::getValue('topup.live_rates_at');
        app(TopupAutopilotJob::class)->handle(...$this->deps());
        $this->assertSame($at, Setting::getValue('topup.live_rates_at'));
    }

    private function deps(): array
    {
        return [app(\App\Services\Topup\TopupOps::class), app(\App\Services\Topup\TopupCatalogue::class), app(\App\Services\Topup\ProviderRouter::class), app(\App\Services\Topup\TopupLiveRates::class)];
    }
}
