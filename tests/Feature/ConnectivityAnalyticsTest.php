<?php

namespace Tests\Feature;

use App\Jobs\CaptureEsimUsageSnapshotJob;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\EsimUsageSnapshot;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Analytics\ConnectivityAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * Connectivity Analytics blueprint Part A — the usage-snapshot pipeline
 * (CaptureEsimUsageSnapshotJob, esim:sync-usage) and the read-only
 * ConnectivityAnalyticsService aggregations.
 */
class ConnectivityAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $overrides = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'has_voice' => false, 'is_active' => true, 'is_featured' => false,
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 1024, 'validity_days' => 7,
            'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ], $overrides));
    }

    private function order(User $user, EsimPlan $plan, array $overrides = []): EsimOrder
    {
        return EsimOrder::create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'esimgo',
            'status' => 'active',
            'iccid' => '8944'.uniqid(),
            'price_charged' => 9,
            'wholesale_cost' => 3,
            'currency' => 'USD',
        ], $overrides));
    }

    // -------------------------- CaptureEsimUsageSnapshotJob --------------------------

    public function test_it_writes_a_snapshot_from_the_confirmed_normalized_shape(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan());
        app()->instance('esim.esimgo', new FakeEsimProvider(usageResponse: ['remaining_mb' => 400, 'used_mb' => 624]));

        (new CaptureEsimUsageSnapshotJob($order->id))->handle();

        $snap = EsimUsageSnapshot::where('esim_order_id', $order->id)->first();
        $this->assertNotNull($snap);
        $this->assertSame(400, $snap->data_remaining_mb);
        $this->assertSame(624, $snap->data_used_mb);
        $this->assertSame(1024, $snap->data_total_mb);
        $this->assertSame(400, $order->fresh()->data_remaining_mb);
    }

    public function test_it_falls_back_to_common_aliases_for_an_unnormalized_provider_payload(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan());
        app()->instance('esim.esimgo', new FakeEsimProvider(usageResponse: ['remaining' => 200, 'total' => 1024, 'status' => 'active']));

        (new CaptureEsimUsageSnapshotJob($order->id))->handle();

        $snap = EsimUsageSnapshot::where('esim_order_id', $order->id)->first();
        $this->assertSame(200, $snap->data_remaining_mb);
        $this->assertSame(1024, $snap->data_total_mb);
        $this->assertSame(824, $snap->data_used_mb); // derived: total - remaining
        $this->assertSame('active', $snap->bundle_status);
    }

    public function test_it_skips_a_non_active_order_without_calling_the_provider(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan(), ['status' => 'expired']);
        $fake = new FakeEsimProvider(usageResponse: ['remaining_mb' => 1]);
        app()->instance('esim.esimgo', $fake);

        (new CaptureEsimUsageSnapshotJob($order->id))->handle();

        $this->assertSame(0, EsimUsageSnapshot::count());
    }

    public function test_a_provider_exception_never_throws_out_of_the_job(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan());
        app()->instance('esim.esimgo', new FakeEsimProvider(shouldThrow: true));

        (new CaptureEsimUsageSnapshotJob($order->id))->handle();

        $this->assertSame(0, EsimUsageSnapshot::count());
    }

    public function test_sync_command_only_queues_active_orders_with_an_iccid(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        $active = $this->order($user, $plan);
        $this->order($user, $plan, ['status' => 'expired', 'iccid' => '8944'.uniqid()]);
        $this->order($user, $plan, ['status' => 'active', 'iccid' => null]);

        Queue::fake();

        $this->artisan('esim:sync-usage')->assertExitCode(0);

        Queue::assertPushed(CaptureEsimUsageSnapshotJob::class, 1);
        Queue::assertPushed(fn (CaptureEsimUsageSnapshotJob $job) => $job->esimOrderId === $active->id);
    }

    // ------------------------------ ConnectivityAnalyticsService ------------------------------

    public function test_usage_timeline_returns_the_snapshot_history_for_one_order(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan());
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 800, 'data_used_mb' => 224, 'captured_at' => now()->subHours(2)]);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 600, 'data_used_mb' => 424, 'captured_at' => now()]);

        $timeline = (new ConnectivityAnalyticsService)->usageTimeline($order);

        $this->assertCount(2, $timeline);
        $this->assertSame(800, $timeline[0]['remaining_mb']);
        $this->assertSame(600, $timeline[1]['remaining_mb']);
    }

    public function test_burn_rate_projects_days_left_from_declining_usage(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan());
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 1000, 'captured_at' => now()->subDays(2)]);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 600, 'captured_at' => now()]);

        $rate = (new ConnectivityAnalyticsService)->usageBurnRate($order);

        $this->assertNotNull($rate);
        $this->assertSame(200.0, $rate['mb_per_day']); // 400mb consumed over 2 days
        $this->assertSame(3.0, $rate['days_left']); // 600 remaining / 200 per day
    }

    public function test_burn_rate_is_null_with_fewer_than_two_snapshots(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan());
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_remaining_mb' => 1000, 'captured_at' => now()]);

        $this->assertNull((new ConnectivityAnalyticsService)->usageBurnRate($order));
    }

    public function test_weekly_data_usage_sums_snapshot_to_snapshot_deltas_not_the_raw_cumulative_column(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, $this->plan());

        // data_used_mb is cumulative since activation — 200 -> 350 -> 500 is
        // 150mb used on each of two distinct days, never the raw 500 figure.
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_used_mb' => 200, 'captured_at' => now()->subDays(2)]);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_used_mb' => 350, 'captured_at' => now()->subDays(1)]);
        EsimUsageSnapshot::create(['esim_order_id' => $order->id, 'user_id' => $user->id, 'data_used_mb' => 500, 'captured_at' => now()]);

        $usage = (new ConnectivityAnalyticsService)->weeklyDataUsage($user, 7);

        $this->assertSame(300.0, $usage['total_mb']);
        $this->assertCount(7, $usage['daily']);
        $this->assertSame(now()->toDateString(), $usage['daily'][6]['date']);
        $this->assertSame(150.0, $usage['daily'][6]['mb']);
    }

    public function test_weekly_data_usage_sums_across_multiple_orders_and_ignores_a_bundle_reset(): void
    {
        $user = User::factory()->create();
        $orderA = $this->order($user, $this->plan(), ['iccid' => '8944'.uniqid()]);
        $orderB = $this->order($user, $this->plan(), ['iccid' => '8944'.uniqid()]);

        EsimUsageSnapshot::create(['esim_order_id' => $orderA->id, 'user_id' => $user->id, 'data_used_mb' => 100, 'captured_at' => now()->subHours(20)]);
        EsimUsageSnapshot::create(['esim_order_id' => $orderA->id, 'user_id' => $user->id, 'data_used_mb' => 180, 'captured_at' => now()]);

        // Bundle B refilled/reset (used_mb dropped) — that step contributes 0,
        // never a negative or invented figure.
        EsimUsageSnapshot::create(['esim_order_id' => $orderB->id, 'user_id' => $user->id, 'data_used_mb' => 900, 'captured_at' => now()->subHours(20)]);
        EsimUsageSnapshot::create(['esim_order_id' => $orderB->id, 'user_id' => $user->id, 'data_used_mb' => 50, 'captured_at' => now()]);

        $usage = (new ConnectivityAnalyticsService)->weeklyDataUsage($user, 7);

        $this->assertSame(80.0, $usage['total_mb']);
    }

    public function test_weekly_data_usage_is_zero_with_no_snapshot_history(): void
    {
        $user = User::factory()->create();

        $usage = (new ConnectivityAnalyticsService)->weeklyDataUsage($user, 7);

        $this->assertSame(0.0, $usage['total_mb']);
        $this->assertCount(7, $usage['daily']);
    }

    public function test_wallet_spend_breakdown_groups_by_public_model_never_provider(): void
    {
        $user = User::factory()->create();
        $this->order($user, $this->plan(['has_voice' => false]), ['price_charged' => 9]);
        $this->order($user, $this->plan(['has_voice' => true]), ['price_charged' => 15]);
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'getatext', 'type' => 'otp', 'status' => 'completed', 'charged_to_user' => 2]);

        $breakdown = collect((new ConnectivityAnalyticsService)->walletSpendBreakdown($user));

        $this->assertSame(9.0, $breakdown->firstWhere('model', 'naara_data')['total']);
        $this->assertSame(15.0, $breakdown->firstWhere('model', 'naara_connect')['total']);
        $this->assertSame(2.0, $breakdown->firstWhere('model', 'naara_verify')['total']);
        $this->assertStringNotContainsString('esimgo', json_encode($breakdown));
        $this->assertStringNotContainsString('getatext', json_encode($breakdown));
    }

    public function test_top_up_history_sums_credits_by_day(): void
    {
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'type' => 'credit', 'amount' => 50, 'currency' => 'USD', 'balance_before' => 0, 'balance_after' => 50]);
        WalletTransaction::create(['user_id' => $user->id, 'type' => 'debit', 'amount' => 9, 'currency' => 'USD', 'balance_before' => 50, 'balance_after' => 41]);

        $history = (new ConnectivityAnalyticsService)->topUpHistory($user);

        $this->assertCount(1, $history);
        $this->assertSame(50.0, $history[0]['total']);
    }

    public function test_plan_mix_breakdown_groups_by_country_and_region_not_provider(): void
    {
        $user = User::factory()->create();
        $this->order($user, $this->plan(['countries' => ['FR']]));
        $this->order($user, $this->plan(['countries' => ['FR']]));
        $this->order($user, $this->plan(['coverage_type' => EsimPlan::COVERAGE_GLOBAL, 'countries' => []]));

        $mix = collect((new ConnectivityAnalyticsService)->planMixBreakdown($user));

        $this->assertSame(2, $mix->firstWhere('label', 'France')['count']);
        $this->assertSame(1, $mix->firstWhere('label', 'Global')['count']);
    }
}
