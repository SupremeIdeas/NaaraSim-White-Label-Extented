<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Jobs\RecomputePlanPricingJob;
use App\Livewire\Admin\ApiGuideModal;
use App\Livewire\Admin\ErrorLogViewer;
use App\Livewire\Admin\Pricing;
use App\Models\ErrorLog;
use App\Models\EsimPlan;
use App\Models\PricingEngineLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Confirmed 2FA so the Module 14 gate lets full-page admin requests
        // through (component tests bypass middleware and don't need this).
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'a-1', 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ])->fresh();
    }

    public function test_admin_routes_are_404_for_non_admins_and_ok_for_admins(): void
    {
        // Guests are sent to the dedicated admin login, then returned.
        $this->get('/adminmaster')->assertRedirect(route('admin.login'));
        // The admin login page itself is reachable by guests.
        $this->get('/adminmaster/login')->assertOk()->assertSee('Administrator access');

        // Authenticated non-admins stay hidden with a plain 404.
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster')->assertNotFound();

        // An admin gets in (2FA is opt-in, off by default).
        $this->actingAs($this->admin())->get('/adminmaster')->assertOk();
    }

    public function test_saving_global_markup_persists_reprices_and_audits(): void
    {
        Queue::fake();

        Livewire::actingAs($this->admin())->test(Pricing::class)
            ->set('default_markup_pct', 55)
            ->set('minimum_profit_usd', 0.75)
            ->call('saveGlobal')
            ->assertSet('saved', 'Global pricing saved — all plans are being repriced.');

        $this->assertSame(55.0, (float) Setting::getValue('pricing.default_markup_pct'));
        Queue::assertPushed(RecomputePlanPricingJob::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pricing.global_updated']);
    }

    public function test_saving_global_persists_the_outbound_sms_send_cost(): void
    {
        Queue::fake();

        Livewire::actingAs($this->admin())->test(Pricing::class)
            ->set('sms_send_cost_twilio', 0.012)
            ->set('sms_send_cost_telnyx', 0.006)
            ->call('saveGlobal')
            ->assertHasNoErrors();

        $this->assertSame(0.012, (float) Setting::getValue('pricing.sms_send_cost.twilio'));
        $this->assertSame(0.006, (float) Setting::getValue('pricing.sms_send_cost.telnyx'));
    }

    public function test_editing_a_plan_shows_live_profit_without_logging(): void
    {
        $plan = $this->plan();

        Livewire::actingAs($this->admin())->test(Pricing::class)
            ->call('editPlan', $plan->id)
            ->assertSee('Live profit')
            // Override 100% -> retail 4.00 * (1 + 100/100) = 8.00, profit 4.00 (live).
            ->set('override_markup_pct', 100)
            ->assertSee('$8.00')
            ->assertSee('$4.00');

        // The live preview must NOT spam pricing_engine_logs.
        $this->assertSame(0, PricingEngineLog::count());
    }

    public function test_saving_a_plan_override_updates_and_audits(): void
    {
        $plan = $this->plan();

        Livewire::actingAs($this->admin())->test(Pricing::class)
            ->call('editPlan', $plan->id)
            ->set('manual_retail_usd', 25)
            ->call('savePlan');

        $this->assertSame('25.0000', (string) $plan->fresh()->manual_retail_usd);
        $this->assertSame(25.0, (float) $plan->fresh()->final_retail_usd);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pricing.plan_updated', 'model_id' => $plan->id]);
    }

    public function test_saving_a_plan_override_flushes_the_storefront_teaser_cache(): void
    {
        // Readiness-audit fix (2026-09-07): a manual price override used to
        // leave the storefront's "from $X" grid cached at the old price
        // forever, since only catalogue-sync/feature-toggle actions flushed it.
        Cache::forever('esim.nav.grid.v1:data', ['stale' => true]);
        $plan = $this->plan();

        Livewire::actingAs($this->admin())->test(Pricing::class)
            ->call('editPlan', $plan->id)
            ->set('manual_retail_usd', 25)
            ->call('savePlan');

        $this->assertFalse(Cache::has('esim.nav.grid.v1:data'));
    }

    public function test_api_guide_modal_opens_with_the_right_content(): void
    {
        Livewire::actingAs($this->admin())->test(ApiGuideModal::class)
            ->dispatch('open-api-guide', provider: 'esimgo', field: 'api_key')
            ->assertSet('open', true)
            ->assertSet('title', 'Esimgo — API Key')
            ->assertSee('ESIMGO_API_KEY')
            ->assertSee('portal.esim-go.com');
    }

    public function test_error_log_exports_csv_and_json_for_a_day(): void
    {
        ErrorLog::create(['code' => 'X1', 'message' => 'boom happened', 'severity' => 'critical']);
        $today = now()->toDateString();

        Livewire::actingAs($this->admin())->test(ErrorLogViewer::class)
            ->assertSet('date', $today)
            ->call('exportCsv')
            ->assertFileDownloaded("error-log-{$today}.csv");

        Livewire::actingAs($this->admin())->test(ErrorLogViewer::class)
            ->call('exportJson')
            ->assertFileDownloaded("error-log-{$today}.json");
    }

    public function test_providers_health_check_caches_balances_and_alerts_low(): void
    {
        Queue::fake();
        config(['services.esimgo.api_key' => 'live-key']);        // Active
        app()->instance('esim.esimgo', new FakeEsimProvider);      // getBalance() = 0.0
        // low_balance_alert.esimgo is seeded at 100 -> 0 < 100 -> low + alert.

        $this->artisan('providers:health-check')->assertSuccessful();

        $health = Cache::get('providers:health');
        $this->assertSame('low', $health['esimgo']['status']);
        $this->assertSame('coming_soon', $health['getatext']['status']); // no key
        Queue::assertPushed(AlertAdminJob::class);
    }
}
