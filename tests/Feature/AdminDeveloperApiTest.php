<?php

namespace Tests\Feature;

use App\Livewire\Admin\DeveloperApi;
use App\Models\ApiClient;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Developer API (ROADMAP §Layer 2): the operator toggles the program and
 * sets the developer markups. Even a reckless markup is floored by MarginGuard.
 */
class AdminDeveloperApiTest extends TestCase
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
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_admin_can_enable_the_api_and_set_markups(): void
    {
        Livewire::actingAs($this->admin())->test(DeveloperApi::class)
            ->set('enabled', true)
            ->set('esimMarkup', 12)
            ->set('smsMarkup', 18)
            ->call('save')
            ->assertSet('saved', fn ($s) => is_string($s));

        $this->assertTrue((bool) Setting::getValue('developer_api.enabled'));
        $this->assertEquals(12, (float) Setting::getValue('pricing.developer_markup_pct'));
        $this->assertEquals(18, (float) Setting::getValue('pricing.developer_sms_markup_pct'));
    }

    public function test_the_saved_markup_flows_through_the_pricing_engine(): void
    {
        Livewire::actingAs($this->admin())->test(DeveloperApi::class)
            ->set('enabled', true)->set('esimMarkup', 20)->set('smsMarkup', 15)->call('save');

        $plan = \App\Models\EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'x', 'name' => 'p',
            'cost_price_usd' => 10.0, 'is_active' => true,
        ]);
        // cost 10 * 1.20 = 12.00 with the new admin markup
        $this->assertSame(12.0, app(PricingEngine::class)->developerEsimPrice($plan, log: false));
    }

    public function test_a_reckless_zero_markup_is_still_floored_by_margin_guard(): void
    {
        Livewire::actingAs($this->admin())->test(DeveloperApi::class)
            ->set('enabled', true)->set('esimMarkup', 0)->set('smsMarkup', 0)->call('save');

        $plan = \App\Models\EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'y', 'name' => 'p2',
            'cost_price_usd' => 10.0, 'is_active' => true,
        ]);
        // 0% → cost 10, floored up to cost + minimum_profit (10 + 0.50).
        $this->assertSame(10.5, app(PricingEngine::class)->developerEsimPrice($plan, log: false));
    }

    public function test_a_non_admin_cannot_open_the_page(): void
    {
        Livewire::actingAs(User::factory()->create())->test(DeveloperApi::class)->assertForbidden();
    }

    public function test_the_page_lists_api_clients_with_totals(): void
    {
        $owner = User::factory()->create(['email' => 'dev@example.com']);
        ApiClient::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'scopes' => ['order'],
            'is_active' => true, 'prepaid_balance_usd' => 25]);

        Livewire::actingAs($this->admin())->test(DeveloperApi::class)
            ->assertSee('Acme')
            ->assertSee('dev@example.com')
            ->assertSee('25.00');
    }
}
