<?php

namespace Tests\Feature;

use App\Models\EsimPlan;
use App\Models\PricingProposal;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\AnthropicClient;
use App\Services\Pricing\PricingArchitect;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeAnthropicClient;
use Tests\TestCase;

/**
 * Plan Price with Claude — the AI Pricing Architect. The load-bearing guarantee:
 * Claude only PROPOSES; MarginGuard is the law. No proposal, however low, can
 * ever set a price at or below cost + minimum profit.
 */
class PricingArchitectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
    }

    private function plan(string $name, float $cost, float $retail): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'pa-'.uniqid(), 'name' => $name,
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => $cost, 'computed_retail_usd' => $retail, 'is_active' => true,
        ])->fresh();
    }

    private function fake(array $json, bool $on = true): FakeAnthropicClient
    {
        $fake = new FakeAnthropicClient($json, $on);
        $this->app->instance(AnthropicClient::class, $fake);

        return $fake;
    }

    public function test_a_below_floor_proposal_is_clamped_up_by_margin_guard(): void
    {
        $cheap = $this->plan('USA 3GB', 4.00, 10.00); // floor = 4.50
        $this->fake([
            'summary' => 'Sharpened for the African market.',
            'recommended_max_discount_pct' => 15,
            'recommended_credit_redeem_pct' => 40,
            'lines' => [
                // Claude (or a bad actor) proposes $0.01 — WELL below the floor.
                ['plan_id' => $cheap->id, 'proposed_retail_usd' => 0.01, 'rationale' => 'aggressive'],
            ],
        ]);

        $proposal = app(PricingArchitect::class)->propose();
        $line = $proposal->lines()->firstOrFail();

        // The guard raised it to exactly the floor; profit is never negative.
        $this->assertSame('4.5000', (string) $line->proposed_retail_usd);
        $this->assertTrue((bool) $line->guard_applied);
        $this->assertGreaterThan(0, (float) $line->projected_profit_usd);
    }

    public function test_a_healthy_proposal_is_kept_and_applied_to_the_plan(): void
    {
        $plan = $this->plan('Europe 5GB', 6.00, 9.00); // floor 6.50
        $this->fake([
            'summary' => 'Room to raise.',
            'lines' => [['plan_id' => $plan->id, 'proposed_retail_usd' => 12.00, 'rationale' => 'premium']],
        ]);

        $architect = app(PricingArchitect::class);
        $proposal = $architect->propose();
        $line = $proposal->lines()->firstOrFail();
        $this->assertSame('12.0000', (string) $line->proposed_retail_usd);
        $this->assertFalse((bool) $line->guard_applied);

        $admin = User::factory()->create()->fresh();
        $applied = $architect->apply($proposal->fresh('lines'), $admin);

        $this->assertSame(1, $applied);
        $this->assertSame('approved', $proposal->fresh()->status);
        // The plan's live retail is now the proposed price (never below floor).
        $this->assertSame('12.0000', (string) $plan->fresh()->final_retail_usd);
    }

    public function test_apply_can_never_push_a_price_below_the_floor_even_if_tampered(): void
    {
        $plan = $this->plan('Asia 1GB', 3.00, 8.00); // floor 3.50
        $this->fake(['lines' => [['plan_id' => $plan->id, 'proposed_retail_usd' => 7.00, 'rationale' => 'ok']]]);

        $architect = app(PricingArchitect::class);
        $proposal = $architect->propose();

        // Tamper with the stored line to a below-floor value, then apply.
        $line = $proposal->lines()->firstOrFail();
        $line->update(['proposed_retail_usd' => 0.10]);

        $admin = User::factory()->create()->fresh();
        $architect->apply($proposal->fresh('lines'), $admin);

        // apply() re-clamps to the floor via the engine — cost never wins.
        $this->assertGreaterThanOrEqual(3.50, (float) $plan->fresh()->final_retail_usd);
    }

    public function test_it_is_disabled_without_an_anthropic_key(): void
    {
        $this->plan('USA 3GB', 4.00, 10.00);
        $architect = new PricingArchitect(new FakeAnthropicClient([], on: false), app(\App\Services\Pricing\PricingEngine::class));

        $this->assertFalse($architect->enabled());

        // The panel shows the "add your key" state, not the analyse button.
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('super_admin');
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient([], on: false));
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\PricingArchitect::class)
            ->assertSet('analyzing', false)
            ->call('analyze')
            ->assertSee('Anthropic API key'); // friendly error, no crash
    }

    public function test_the_admin_panel_analyses_then_approves_end_to_end(): void
    {
        $plan = $this->plan('Global 10GB', 8.00, 11.00); // floor 8.50
        $this->fake([
            'summary' => 'Priced to win.',
            'recommended_credit_redeem_pct' => 35,
            'lines' => [['plan_id' => $plan->id, 'proposed_retail_usd' => 13.50, 'rationale' => 'headroom']],
        ]);

        $admin = User::factory()->create()->fresh();
        $admin->assignRole('super_admin');

        // Analyse → the (sync-queued) job creates a pending proposal shown on screen.
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\PricingArchitect::class)
            ->call('analyze')
            ->assertSee('Priced to win.')
            ->assertSee('13.50')
            ->call('approve')
            ->assertDispatched('nx-toast', variant: 'hero', type: 'success');

        $this->assertSame('13.5000', (string) $plan->fresh()->final_retail_usd);
        $this->assertSame('approved', PricingProposal::latest()->firstOrFail()->status);
        // The recommended credit-redeem cap was applied too.
        $this->assertSame(35, (int) Setting::getValue('credits.max_redeem_pct'));
    }

    public function test_the_monitor_flags_a_plan_sitting_at_its_floor(): void
    {
        // Retail forced to the floor via a manual price at cost + min profit.
        $plan = $this->plan('Thin 1GB', 4.00, 4.50);
        $plan->update(['manual_retail_usd' => 4.50]);

        $monitor = app(PricingArchitect::class)->monitor();
        $this->assertSame(1, $monitor['at_floor']);
    }

    public function test_cost_never_leaks_into_a_plans_public_array(): void
    {
        $plan = $this->plan('USA 3GB', 4.00, 10.00);
        // The plan model hides cost; the architect's admin-only line stores it,
        // but the plan itself must never serialise cost to a user payload.
        $this->assertArrayNotHasKey('cost_price_usd', $plan->fresh()->toArray());
    }
}
