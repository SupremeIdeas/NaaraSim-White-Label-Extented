<?php

namespace Tests\Feature;

use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\EsimUsageSnapshot;
use App\Models\GiftCardOrder;
use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantClientSubscription;
use App\Models\MerchantEarning;
use App\Models\OrderLog;
use App\Models\PaymentCharge;
use App\Models\PaymentRefund;
use App\Models\ProviderRegistry;
use App\Models\SmsOrder;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use App\Services\Analytics\PlatformAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Analytics blueprint §7.2 — PlatformAnalyticsService's revenue & profit
 * group. Fixes the confirmed gap: Naara Gift revenue used to be invisible in
 * every admin revenue/profit figure. Profit deliberately excludes Naara Gift
 * (no per-order provider cost is ever persisted for gift cards) — see the
 * service's own doc block.
 */
class PlatformAnalyticsServiceTest extends TestCase
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

    public function test_revenue_breakdown_includes_naara_gift_as_its_own_segment(): void
    {
        $user = User::factory()->create();
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'twilio', 'type' => 'permanent', 'status' => 'completed', 'charged_to_user' => 5, 'provider_cost' => 1]);
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'getatext', 'type' => 'otp', 'status' => 'completed', 'charged_to_user' => 2, 'provider_cost' => 0.5]);
        GiftCardOrder::create([
            'user_id' => $user->id, 'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 50, 'currency' => 'USD', 'price_charged' => 52, 'status' => GiftCardOrder::STATUS_DELIVERED,
            'transaction_ref' => 'gift-1',
        ]);

        $breakdown = (new PlatformAnalyticsService)->revenueBreakdown('30d');
        $segments = collect($breakdown['segments'])->keyBy('label');

        $this->assertSame(9.0, $segments['eSIM data']['value']);
        $this->assertSame(5.0, $segments['Virtual numbers']['value']);
        $this->assertSame(2.0, $segments['Verification']['value']);
        $this->assertSame(52.0, $segments['Naara Gift']['value']);
        $this->assertSame(68.0, $breakdown['total']);
        // Never a raw provider slug in the chart-ready payload.
        $this->assertStringNotContainsString('reloadly', json_encode($breakdown));
        $this->assertStringNotContainsString('twilio', json_encode($breakdown));
    }

    public function test_a_failed_or_refunded_gift_card_order_never_counts_as_revenue(): void
    {
        $user = User::factory()->create();
        GiftCardOrder::create([
            'user_id' => $user->id, 'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 50, 'currency' => 'USD', 'price_charged' => 52, 'status' => GiftCardOrder::STATUS_FAILED,
            'transaction_ref' => 'gift-failed',
        ]);
        GiftCardOrder::create([
            'user_id' => $user->id, 'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 50, 'currency' => 'USD', 'price_charged' => 52, 'status' => GiftCardOrder::STATUS_REFUNDED,
            'transaction_ref' => 'gift-refunded',
        ]);

        $breakdown = (new PlatformAnalyticsService)->revenueBreakdown('30d');
        $segments = collect($breakdown['segments'])->keyBy('label');

        $this->assertSame(0.0, $segments['Naara Gift']['value']);
    }

    public function test_profit_window_excludes_naara_gift_since_its_cost_is_never_persisted(): void
    {
        $user = User::factory()->create();
        $order = EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);
        OrderLog::create(['user_id' => $user->id, 'provider' => 'esimgo', 'provider_cost' => 3, 'charged_to_user' => 9, 'profit' => 6, 'profit_pct' => 66.7, 'result' => 'success']);
        GiftCardOrder::create([
            'user_id' => $user->id, 'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 500, 'currency' => 'USD', 'price_charged' => 520, 'status' => GiftCardOrder::STATUS_DELIVERED,
            'transaction_ref' => 'gift-big',
        ]);

        $profit = (new PlatformAnalyticsService)->profitWindow(now()->subDays(30));

        // 9 revenue - 3 cost = 6. The 520 gift card sale must NOT inflate this.
        $this->assertSame(6.0, $profit);
    }

    public function test_daily_revenue_bars_include_gift_card_sales_since_revenue_alone_has_no_cost_ambiguity(): void
    {
        $user = User::factory()->create();
        GiftCardOrder::create([
            'user_id' => $user->id, 'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 20, 'currency' => 'USD', 'price_charged' => 21, 'status' => GiftCardOrder::STATUS_DELIVERED,
            'transaction_ref' => 'gift-today',
        ]);

        $bars = (new PlatformAnalyticsService)->dailyRevenueBars(7);

        $this->assertCount(7, $bars);
        $this->assertSame(now()->toDateString(), $bars[6]['date']);
        $this->assertSame(21.0, $bars[6]['value']);
    }

    public function test_revenue_trend_compares_to_the_prior_equal_length_window(): void
    {
        $user = User::factory()->create();
        $old = EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 20, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);
        $old->timestamps = false;
        $old->forceFill(['created_at' => now()->subDays(45), 'updated_at' => now()->subDays(45)])->save();

        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 30, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);

        $trend = (new PlatformAnalyticsService)->revenueTrend('30d');

        $this->assertSame(30.0, $trend['current']);
        $this->assertSame(20.0, $trend['previous']);
        $this->assertSame(50.0, $trend['delta_pct']);
    }

    // -------------------------- Wallet & FX group --------------------------

    public function test_top_up_volume_by_gateway_sums_payment_charges(): void
    {
        PaymentCharge::create(['gateway' => 'paystack', 'reference' => 'ref-1', 'amount' => 50, 'currency' => 'USD']);
        PaymentCharge::create(['gateway' => 'paystack', 'reference' => 'ref-2', 'amount' => 30, 'currency' => 'USD']);
        PaymentCharge::create(['gateway' => 'stripe', 'reference' => 'ref-3', 'amount' => 100, 'currency' => 'USD']);

        $byGateway = collect((new PlatformAnalyticsService)->topUpVolumeByGateway('30d'))->keyBy('gateway');

        $this->assertSame(80.0, $byGateway['paystack']['volume']);
        $this->assertSame(100.0, $byGateway['stripe']['volume']);
    }

    public function test_top_up_volume_by_currency_uses_the_original_paid_currency_not_the_credited_one(): void
    {
        $user = User::factory()->create();
        // A converted NGN top-up: credited in USD, but paid_currency/paid_amount
        // records what the user actually typed in — that's what must show here.
        WalletTransaction::create([
            'user_id' => $user->id, 'type' => 'credit', 'amount' => 10, 'currency' => 'USD',
            'paid_amount' => 15000, 'paid_currency' => 'NGN', 'balance_before' => 0, 'balance_after' => 10,
        ]);
        // A direct USD credit: no conversion, paid_currency is null.
        WalletTransaction::create([
            'user_id' => $user->id, 'type' => 'credit', 'amount' => 25, 'currency' => 'USD',
            'balance_before' => 10, 'balance_after' => 35,
        ]);

        $byCurrency = collect((new PlatformAnalyticsService)->topUpVolumeByCurrency('30d'))->keyBy('currency');

        $this->assertSame(15000.0, $byCurrency['NGN']['volume']);
        $this->assertSame(25.0, $byCurrency['USD']['volume']);
    }

    public function test_platform_usd_liability_sums_every_wallets_spendable_balance(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        UserWallet::create(['user_id' => $userA->id, 'usd_balance' => 40, 'ngn_balance' => 0]);
        UserWallet::create(['user_id' => $userB->id, 'usd_balance' => 60, 'ngn_balance' => 0]);

        $liability = (new PlatformAnalyticsService)->platformUsdLiability();

        $this->assertSame(100.0, $liability);
    }

    public function test_fx_rate_snapshot_covers_every_supported_currency_and_is_never_zero(): void
    {
        // Free open.er-api.com feed (no key) — faked for deterministic tests.
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'rates' => ['USD' => 1, 'GBP' => 0.80, 'EUR' => 0.90, 'GHS' => 15.5, 'ZAR' => 18.0, 'KES' => 130, 'CAD' => 1.35, 'INR' => 83, 'USDT' => 1, 'NGN' => 1600],
            ]),
        ]);

        $snapshot = collect((new PlatformAnalyticsService)->fxRateSnapshot())->keyBy('currency');

        $this->assertSame(1.0, $snapshot['USD']['usd_rate']);
        $this->assertGreaterThan(0, $snapshot['NGN']['usd_rate']);
        $this->assertGreaterThan(0, $snapshot['GHS']['usd_rate']);
    }

    // -------------------------- Merchant group --------------------------

    public function test_merchant_volume_leaderboard_ranks_by_client_order_volume(): void
    {
        $owner1 = User::factory()->create();
        $owner2 = User::factory()->create();
        $merchantA = Merchant::create(['owner_user_id' => $owner1->id, 'business_name' => 'Alpha Travel', 'slug' => 'alpha-travel-'.uniqid(), 'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_V2]);
        $merchantB = Merchant::create(['owner_user_id' => $owner2->id, 'business_name' => 'Beta Roam', 'slug' => 'beta-roam-'.uniqid(), 'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_V2]);

        $clientA = MerchantClient::create(['merchant_id' => $merchantA->id, 'name' => 'Client A']);
        $clientB = MerchantClient::create(['merchant_id' => $merchantB->id, 'name' => 'Client B']);

        $plan = EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 1024, 'validity_days' => 7, 'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ]);
        $orderA = EsimOrder::create(['user_id' => $owner1->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 50, 'wholesale_cost' => 3, 'currency' => 'USD']);
        $orderB = EsimOrder::create(['user_id' => $owner2->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 20, 'wholesale_cost' => 3, 'currency' => 'USD']);

        MerchantClientSubscription::create(['merchant_id' => $merchantA->id, 'merchant_client_id' => $clientA->id, 'esim_order_id' => $orderA->id, 'plan_id' => $plan->id, 'esim_type' => 'data', 'status' => 'active']);
        MerchantClientSubscription::create(['merchant_id' => $merchantB->id, 'merchant_client_id' => $clientB->id, 'esim_order_id' => $orderB->id, 'plan_id' => $plan->id, 'esim_type' => 'data', 'status' => 'active']);

        $leaderboard = (new PlatformAnalyticsService)->merchantVolumeLeaderboard('30d');

        $this->assertSame('Alpha Travel', $leaderboard[0]['business_name']);
        $this->assertSame(50.0, $leaderboard[0]['volume']);
        $this->assertSame(1, $leaderboard[0]['clients']);
        $this->assertSame('Beta Roam', $leaderboard[1]['business_name']);
        $this->assertSame(20.0, $leaderboard[1]['volume']);
    }

    public function test_merchant_earnings_total_sums_only_accruals_in_the_window(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $owner->id, 'business_name' => 'Alpha Travel', 'slug' => 'alpha-travel-'.uniqid(), 'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_V2]);

        MerchantEarning::create(['merchant_id' => $merchant->id, 'type' => MerchantEarning::ACCRUAL, 'amount' => 12, 'balance_after' => 12, 'currency' => 'USD', 'reference' => 'earn-1']);
        MerchantEarning::create(['merchant_id' => $merchant->id, 'type' => MerchantEarning::ACCRUAL, 'amount' => 8, 'balance_after' => 20, 'currency' => 'USD', 'reference' => 'earn-2']);
        MerchantEarning::create(['merchant_id' => $merchant->id, 'type' => MerchantEarning::RELEASE, 'amount' => 20, 'balance_after' => 0, 'currency' => 'USD', 'reference' => 'earn-3']);

        $total = (new PlatformAnalyticsService)->merchantEarningsTotal('30d');

        $this->assertSame(20.0, $total);
    }

    // -------------------------- Operational health group --------------------------

    public function test_kyc_approval_rate_only_counts_final_decisions(): void
    {
        $user = User::factory()->create();
        KycVerification::create(['user_id' => $user->id, 'level' => KycVerification::L2, 'provider' => 'sumsub', 'status' => KycVerification::APPROVED, 'reference' => 'kyc-1']);
        KycVerification::create(['user_id' => $user->id, 'level' => KycVerification::L2, 'provider' => 'sumsub', 'status' => KycVerification::REJECTED, 'reference' => 'kyc-2']);
        KycVerification::create(['user_id' => $user->id, 'level' => KycVerification::L2, 'provider' => 'sumsub', 'status' => KycVerification::PENDING, 'reference' => 'kyc-3']);

        $rate = (new PlatformAnalyticsService)->kycApprovalRate('30d');

        $this->assertSame(1, $rate['approved']);
        $this->assertSame(2, $rate['total']); // pending is excluded — not a decision yet
        $this->assertSame(50.0, $rate['rate_pct']);
    }

    public function test_refund_rate_vs_revenue_only_counts_settled_refunds(): void
    {
        $user = User::factory()->create();
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 100, 'wholesale_cost' => 3, 'currency' => 'USD',
        ]);
        PaymentRefund::create(['user_id' => $user->id, 'gateway' => 'paystack', 'reference' => 'ref-1', 'amount' => 10, 'currency' => 'USD', 'status' => PaymentRefund::STATUS_DONE]);
        PaymentRefund::create(['user_id' => $user->id, 'gateway' => 'paystack', 'reference' => 'ref-2', 'amount' => 30, 'currency' => 'USD', 'status' => PaymentRefund::STATUS_PENDING]);

        $refundRate = (new PlatformAnalyticsService)->refundRateVsRevenue('30d');

        $this->assertSame(10.0, $refundRate['refunds']);
        $this->assertSame(100.0, $refundRate['revenue']);
        $this->assertSame(10.0, $refundRate['rate_pct']);
    }

    public function test_support_queue_trend_separates_open_from_resolved_and_averages_resolution_time(): void
    {
        $user = User::factory()->create();
        SupportConversation::create(['user_id' => $user->id, 'status' => 'open']);
        $resolved = SupportConversation::create(['user_id' => $user->id, 'status' => 'open']);
        $resolved->timestamps = false;
        $resolved->forceFill(['created_at' => now()->subHours(4)])->save();
        $resolved->timestamps = true;
        $resolved->update(['status' => 'resolved']);

        $trend = (new PlatformAnalyticsService)->supportQueueTrend('30d');

        $this->assertSame(1, $trend['open']);
        $this->assertSame(1, $trend['resolved']);
        $this->assertEqualsWithDelta(4.0, $trend['avg_resolution_hours'], 0.1);
    }

    // -------------------------- Provider reliability + eSIM usage --------------------------

    public function test_provider_reliability_summary_reads_the_existing_nci_registry_not_a_new_tracker(): void
    {
        ProviderRegistry::create(['provider_key' => 'esimgo', 'stack' => 'esim', 'success_rate_24h' => 0.98, 'circuit_breaker_state' => 'closed', 'enabled' => true]);
        ProviderRegistry::create(['provider_key' => 'airalo', 'stack' => 'esim', 'success_rate_24h' => 0.60, 'circuit_breaker_state' => 'open', 'enabled' => true]);
        ProviderRegistry::create(['provider_key' => 'quibity', 'stack' => 'esim', 'success_rate_24h' => null, 'circuit_breaker_state' => 'closed', 'enabled' => true]);
        ProviderRegistry::create(['provider_key' => 'disabled-one', 'stack' => 'esim', 'success_rate_24h' => 0.10, 'circuit_breaker_state' => 'open', 'enabled' => false]);

        $summary = (new PlatformAnalyticsService)->providerReliabilitySummary();

        $this->assertSame(2, $summary['tracked']); // null excluded, disabled excluded
        $this->assertSame(1, $summary['degraded']); // circuit open, enabled only
        $this->assertSame(79.0, $summary['avg_success_rate_pct']); // (98+60)/2
        $this->assertSame('airalo', $summary['worst'][0]['provider_key']);
        $this->assertSame(60.0, $summary['worst'][0]['success_rate_pct']);
    }

    public function test_platform_esim_usage_summary_sums_real_snapshot_deltas_across_every_order(): void
    {
        $user = User::factory()->create();
        $orderA = EsimOrder::create(['user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD']);
        $orderB = EsimOrder::create(['user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD']);

        EsimUsageSnapshot::create(['esim_order_id' => $orderA->id, 'user_id' => $user->id, 'data_used_mb' => 100, 'captured_at' => now()->subDays(2)]);
        EsimUsageSnapshot::create(['esim_order_id' => $orderA->id, 'user_id' => $user->id, 'data_used_mb' => 300, 'captured_at' => now()]);
        EsimUsageSnapshot::create(['esim_order_id' => $orderB->id, 'user_id' => $user->id, 'data_used_mb' => 50, 'captured_at' => now()->subDays(2)]);
        EsimUsageSnapshot::create(['esim_order_id' => $orderB->id, 'user_id' => $user->id, 'data_used_mb' => 150, 'captured_at' => now()]);

        $summary = (new PlatformAnalyticsService)->platformEsimUsageSummary();

        $this->assertSame(300.0, $summary['total_mb_this_week']); // 200 (A) + 100 (B)
        $this->assertSame(2, $summary['active_esims']);
    }

    // -------------------------- Tier 5 #15 additions --------------------------

    public function test_provider_order_volume_counts_esim_orders_by_provider(): void
    {
        $user = User::factory()->create();
        EsimOrder::create(['user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD']);
        EsimOrder::create(['user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'esimgo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD']);
        EsimOrder::create(['user_id' => $user->id, 'plan_id' => $this->plan()->id, 'provider' => 'airalo', 'status' => 'active', 'price_charged' => 9, 'wholesale_cost' => 3, 'currency' => 'USD']);

        $volume = (new PlatformAnalyticsService)->providerOrderVolume('esim', 'esimgo', '30d');

        $this->assertSame(2, $volume);
    }

    public function test_provider_order_volume_counts_sms_orders_excluding_timeout_and_cancelled(): void
    {
        $user = User::factory()->create();
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'type' => 'otp', 'status' => 'completed', 'charged_to_user' => 2, 'provider_cost' => 0.5]);
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'type' => 'otp', 'status' => 'completed', 'charged_to_user' => 2, 'provider_cost' => 0.5]);
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'type' => 'otp', 'status' => 'timeout', 'charged_to_user' => 0, 'provider_cost' => 0]);
        SmsOrder::create(['user_id' => $user->id, 'provider' => 'fivesim', 'type' => 'otp', 'status' => 'cancelled', 'charged_to_user' => 0, 'provider_cost' => 0]);

        $volume = (new PlatformAnalyticsService)->providerOrderVolume('sms', 'fivesim', '30d');

        $this->assertSame(2, $volume);
    }

    public function test_provider_order_volume_is_zero_for_a_provider_with_no_orders(): void
    {
        $volume = (new PlatformAnalyticsService)->providerOrderVolume('esim', 'quibity', '30d');

        $this->assertSame(0, $volume);
    }

    public function test_kyc_automation_resolution_rate_splits_automated_vs_escalated_final_decisions(): void
    {
        $user = User::factory()->create();
        KycVerification::create(['user_id' => $user->id, 'level' => KycVerification::L2, 'provider' => 'sumsub', 'status' => KycVerification::APPROVED, 'reference' => 'kyc-auto-1']);
        KycVerification::create(['user_id' => $user->id, 'level' => KycVerification::L2, 'provider' => 'dojah', 'status' => KycVerification::APPROVED, 'reference' => 'kyc-auto-2']);
        KycVerification::create(['user_id' => $user->id, 'level' => KycVerification::L3, 'provider' => 'manual', 'status' => KycVerification::APPROVED, 'reference' => 'kyc-manual-1']);
        KycVerification::create(['user_id' => $user->id, 'level' => KycVerification::L2, 'provider' => 'sumsub', 'status' => KycVerification::PENDING, 'reference' => 'kyc-pending-1']);

        $result = (new PlatformAnalyticsService)->kycAutomationResolutionRate('30d');

        $this->assertSame(2, $result['automated']);
        $this->assertSame(1, $result['escalated']);
        $this->assertSame(3, $result['total']); // pending excluded — not a final decision
        $this->assertSame(66.7, $result['automated_pct']);
    }

    public function test_kyc_automation_resolution_rate_is_null_pct_when_no_decisions_yet(): void
    {
        $result = (new PlatformAnalyticsService)->kycAutomationResolutionRate('30d');

        $this->assertSame(0, $result['total']);
        $this->assertNull($result['automated_pct']);
    }
}
