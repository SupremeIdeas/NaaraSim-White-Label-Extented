<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\EsimUsageSnapshot;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Connectivity Analytics blueprint Part A §2.5 — the Home hero card. Real,
 * non-invented figures only (weeklyDataUsage snapshot deltas + wallet_transactions
 * sums), dependency-free SVG sparklines, hidden entirely for a brand-new account.
 */
class DashboardAnalyticsHeroTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 1024, 'validity_days' => 7,
            'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ]);
    }

    public function test_a_brand_new_account_never_sees_the_analytics_hero(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('Data used this week')
            ->assertDontSee('Cash flow (30d)');
    }

    public function test_an_account_with_a_line_but_no_history_sees_graceful_empty_states(): void
    {
        $user = User::factory()->create();
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Data used this week')
            ->assertSee('No usage yet this week.')
            ->assertSee('No wallet activity yet this month.');
    }

    public function test_real_usage_and_wallet_flow_render_on_the_hero(): void
    {
        $user = User::factory()->create();
        $order = EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_used_mb' => 100, 'captured_at' => now()->subDay()]);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_used_mb' => 1636, 'captured_at' => now()]);

        WalletTransaction::create(['user_id' => $user->id, 'type' => 'credit', 'amount' => 50, 'currency' => 'USD', 'balance_before' => 0, 'balance_after' => 50]);
        WalletTransaction::create(['user_id' => $user->id, 'type' => 'debit', 'amount' => 9, 'currency' => 'USD', 'balance_before' => 50, 'balance_after' => 41]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('1.5 GB') // 1536mb used, real snapshot delta
            ->assertDontSee('No usage yet this week.')
            ->assertDontSee('No wallet activity yet this month.');
    }
}
