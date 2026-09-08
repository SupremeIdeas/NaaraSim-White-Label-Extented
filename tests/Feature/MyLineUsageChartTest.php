<?php

namespace Tests\Feature;

use App\Livewire\MyLines;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\EsimUsageSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Connectivity Analytics blueprint Part A §2.6 — the expandable "Usage" panel
 * per active eSIM on My Line: real burn-rate text + a snapshot-history chart,
 * with a graceful message when there isn't enough history yet.
 */
class MyLineUsageChartTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 5120, 'validity_days' => 30,
            'cost_price_usd' => 4, 'computed_retail_usd' => 12,
        ]);
    }

    private function order(User $user): EsimOrder
    {
        return EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 12, 'wholesale_cost' => 4, 'currency' => 'USD',
        ]);
    }

    public function test_a_line_with_two_or_more_snapshots_shows_real_burn_rate_text_and_a_chart(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 5000, 'data_used_mb' => 120, 'captured_at' => now()->subDays(2)]);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 4600, 'data_used_mb' => 520, 'captured_at' => now()]);

        Livewire::actingAs($user)->test(MyLines::class)
            ->assertSee('Show usage')
            ->assertSee('Using about')
            ->assertSee('days of data left at this pace')
            ->assertDontSee('Not enough history yet to chart usage');
    }

    public function test_a_line_with_fewer_than_two_snapshots_shows_the_graceful_empty_state(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 5000, 'data_used_mb' => 120, 'captured_at' => now()]);

        Livewire::actingAs($user)->test(MyLines::class)
            ->assertSee('Show usage')
            ->assertSee('Not enough history yet to chart usage')
            ->assertDontSee('days of data left at this pace');
    }

    public function test_a_line_with_no_snapshots_at_all_never_throws_and_shows_the_empty_state(): void
    {
        $user = User::factory()->create();
        $this->order($user);

        Livewire::actingAs($user)->test(MyLines::class)
            ->assertOk()
            ->assertSee('Not enough history yet to chart usage');
    }
}
