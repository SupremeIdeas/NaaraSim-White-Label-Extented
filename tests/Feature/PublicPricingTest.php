<?php

namespace Tests\Feature;

use App\Livewire\Admin\Pricing as AdminPricing;
use App\Livewire\PricingPage;
use App\Models\EsimPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingEducator;
use App\Support\PricingDisplay;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 29 — public pricing page (live plans / estimate tiers) + AI-assisted
 * pricing education. Money-safety: cost is never surfaced.
 */
class PublicPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        $this->seed(RoleSeeder::class);
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
    }

    private function plan(array $extra = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo',
            'provider_plan_id' => 'pp-'.uniqid(),
            'name' => 'USA 3GB 30D',
            'data_mb' => 3072,
            'validity_days' => 30,
            'countries' => ['US'],
            'cost_price_usd' => 3.77,       // PRIVATE
            'computed_retail_usd' => 10.00,
            'is_active' => true,
        ], $extra))->fresh();
    }

    public function test_estimate_mode_shows_from_tiers_and_never_leaks_cost(): void
    {
        // No provider key configured, no plans → auto resolves to estimate.
        $this->assertSame('estimate', PricingDisplay::mode());

        $this->get('/pricing')
            ->assertOk()
            ->assertSee('Plans That Make Sense')
            ->assertSee('Traveller')          // default estimate tier
            ->assertSee('from')
            ->assertSee('Indicative');         // estimate banner
    }

    public function test_admin_can_force_live_mode_and_real_plans_render_without_cost(): void
    {
        $this->plan(['is_featured' => true]);
        Setting::setValue('pricing.public_mode', 'live');

        $this->assertSame('live', PricingDisplay::mode());

        $this->get('/pricing')
            ->assertOk()
            ->assertSee('USA 3GB 30D')
            ->assertSee('$10.00')             // retail
            ->assertSee('Most popular')       // featured badge
            ->assertDontSee('$3.77')          // cost never shown as a price
            ->assertDontSee('cost_price_usd');
    }

    public function test_admin_can_force_estimate_mode_even_with_live_plans(): void
    {
        $this->plan();
        Setting::setValue('pricing.public_mode', 'estimate');

        $this->assertSame('estimate', PricingDisplay::mode());
        $this->get('/pricing')->assertOk()->assertSee('Traveller')->assertDontSee('USA 3GB 30D');
    }

    public function test_the_explainer_returns_deterministic_education_without_an_api_key(): void
    {
        config(['services.anthropic.api_key' => null]);

        $out = app(PricingEducator::class)->explain([
            'name' => 'USA 3GB 30D', 'data_mb' => 3072, 'validity_days' => 30,
            'countries' => 1, 'price' => '$10.00',
        ]);

        $this->assertFalse($out['ai']);
        $this->assertStringContainsString('GB', $out['summary']);
        $this->assertNotEmpty($out['tips']);
    }

    public function test_explain_action_populates_an_inline_explanation(): void
    {
        $plan = $this->plan();
        Setting::setValue('pricing.public_mode', 'live');

        Livewire::test(PricingPage::class)
            ->call('explain', (string) $plan->id)
            ->assertSet('openExplainer', (string) $plan->id)
            ->assertSee('What does this mean for me?')
            ->assertSee('GB');
    }

    public function test_admin_saves_public_mode_and_estimate_tiers(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(AdminPricing::class)
            ->set('public_mode', 'estimate')
            ->set('estimate_tiers', [
                ['name' => 'Starter', 'from_usd' => 4, 'data' => '500 MB', 'validity' => '3 days', 'blurb' => 'A quick trip.'],
            ])
            ->call('savePublicPricing')
            ->assertHasNoErrors();

        $this->assertSame('estimate', (string) Setting::getValue('pricing.public_mode'));
        $this->assertSame('Starter', PricingDisplay::estimateTiers()[0]['name']);
    }

    public function test_pricing_link_is_in_the_marketing_nav(): void
    {
        $this->get('/')->assertOk()->assertSee(route('pricing'), false);
    }
}
