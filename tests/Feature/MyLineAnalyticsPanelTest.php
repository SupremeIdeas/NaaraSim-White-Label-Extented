<?php

namespace Tests\Feature;

use App\Livewire\MyLines;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Connectivity Analytics blueprint Part A §2.4/2.7 — the collapsed "My
 * Analytics" panel on My Line: plan mix, purchase cadence, spend by public
 * Model, and deposit history. Real, non-invented figures only; each chart
 * degrades to a plain message when there's nothing to plot yet.
 */
class MyLineAnalyticsPanelTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $overrides = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 1024, 'validity_days' => 7,
            'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ], $overrides));
    }

    public function test_the_panel_is_hidden_for_a_brand_new_account(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(MyLines::class)
            ->assertDontSee('My Analytics');
    }

    public function test_an_account_with_a_line_but_no_deposits_sees_a_graceful_top_up_empty_state(): void
    {
        $user = User::factory()->create();
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);

        // The order itself IS real spend (walletSpendBreakdown reads orders
        // directly, never wallet_transactions), so only deposits are empty
        // here — no WalletTransaction credit exists yet.
        Livewire::actingAs($user)->test(MyLines::class)
            ->assertSee('My Analytics')
            ->assertSee('Where you connect')
            ->assertSee('No wallet top-ups in the last 90 days yet.');
    }

    public function test_real_purchase_and_wallet_activity_renders_charts_not_empty_states(): void
    {
        $user = User::factory()->create();
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan(['countries' => ['FR']])->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);
        WalletTransaction::create(['user_id' => $user->id, 'type' => 'credit', 'amount' => 50, 'currency' => 'USD', 'balance_before' => 0, 'balance_after' => 50]);
        WalletTransaction::create(['user_id' => $user->id, 'type' => 'debit', 'amount' => 9, 'currency' => 'USD', 'balance_before' => 50, 'balance_after' => 41]);

        $html = Livewire::actingAs($user)->test(MyLines::class)
            ->assertSee('My Analytics')
            ->assertDontSee('No spend in the last 30 days yet.')
            ->assertDontSee('No wallet top-ups in the last 90 days yet.')
            ->assertDontSee('Buy an eSIM to see this.');

        // Never a raw provider slug anywhere in the chart payloads.
        $html->assertDontSee('esimgo');
    }
}
