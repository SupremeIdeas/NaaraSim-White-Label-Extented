<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Pricing\CouponEngine;
use App\Services\Pricing\DiscountMarginGuard;
use App\Support\CreditSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Margin-safe discount floor (blueprint §6, financial-safety-critical). The
 * combined coupon + NaaraCredit discount may never cross a percentage-of-MARGIN
 * floor, with the old absolute cost+minProfit floor kept underneath (higher wins).
 */
class DiscountMarginFloorTest extends TestCase
{
    use RefreshDatabase;

    private function coupon(int $pct): Coupon
    {
        return Coupon::create([
            'code' => 'SAVE'.$pct, 'percent_off' => $pct, 'active' => true,
            'times_redeemed' => 0, 'max_redemptions' => null,
        ]);
    }

    /** §6.1 — high-margin plan, coupon alone: floors at margin cap, not the old $0.50. */
    public function test_high_margin_coupon_floors_at_margin_cap_not_flat(): void
    {
        Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
        Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');

        // $50 retail / $10 cost → $40 admin margin. 90% coupon wants $5.
        $q = app(CouponEngine::class)->price($this->coupon(90), 50.0, 10.0, 'esim', 40.0, null);

        // Floor = 10 + 40*0.70 = 38 (NOT the old 10 + 0.50 = 10.50).
        $this->assertEqualsWithDelta(38.0, $q['price'], 0.001);
    }

    /** §6.2 — same plan, credits alone: same floor. */
    public function test_high_margin_credits_floor_matches(): void
    {
        Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
        Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');
        Setting::setValue('credits.enabled', true, 'credits');
        Setting::setValue('credits.max_redeem_pct', 100, 'credits');
        \Illuminate\Support\Facades\Cache::flush();

        $user = User::factory()->create();
        // Give the user plenty of withdrawable credits.
        app(CreditService::class)->earn($user, CreditSettings::usdToCredits(100), 'test', 'seed:'.$user->id);

        $q = app(CreditService::class)->quoteRedemption($user, 50.0, 10.0, 40.0, null);
        // Redeemable USD = retail − floor = 50 − 38 = 12.
        $this->assertEqualsWithDelta(12.0, $q['usd'], 0.001);
    }

    /** §6.4 — thin-margin plan: the OLD absolute $0.50 floor still wins. */
    public function test_thin_margin_keeps_absolute_floor(): void
    {
        Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
        Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');

        // $5 retail / $4.80 cost → $0.20 margin. 30% of that = $0.06 → floor would
        // be 4.86, but the absolute 4.80 + 0.50 = 5.30 is capped to retail 5.00.
        $guard = app(DiscountMarginGuard::class);
        $floor = $guard->floor(4.80, 0.20, null, 0.50);
        $this->assertEqualsWithDelta(5.30, $floor, 0.001); // absolute floor wins

        $q = app(CouponEngine::class)->price($this->coupon(90), 5.0, 4.80, 'esim', 0.20, null);
        $this->assertEqualsWithDelta(5.0, $q['price'], 0.001); // clamped to retail, never below cost
    }

    /** §2b/§6.5 — merchant lane: the merchant's earning is NON-ZERO on a discounted order. */
    public function test_merchant_earning_is_not_zeroed_by_a_coupon(): void
    {
        Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
        Setting::setValue('pricing.merchant_discount_admin_pct', 20, 'pricing');
        Setting::setValue('pricing.merchant_discount_merchant_pct', 10, 'pricing');

        // cost 10, plainRetail 50 (admin margin 40), merchant price 60 (merchant margin 10).
        $adminMargin = 40.0;
        $merchantMargin = 10.0;
        $q = app(CouponEngine::class)->price($this->coupon(90), 60.0, 10.0, 'esim', $adminMargin, $merchantMargin);

        // Floor = 10 + 40*0.80 + 10*0.90 = 10 + 32 + 9 = 51. Charge floors at 51.
        $this->assertEqualsWithDelta(51.0, $q['price'], 0.001);

        // The merchant's accrual = charged − plainRetail = 51 − 50 = 1.00 (NON-zero),
        // proving §1.4's zeroing bug is fixed upstream by the floor.
        $merchant = $this->makeMerchant();
        $earning = app(MerchantEarningsService::class)->accrue(
            $merchant, User::factory()->create(), 'esim', 50.0, $q['price'], 'earn:test1',
        );
        $this->assertNotNull($earning);
        $this->assertEqualsWithDelta(1.00, (float) $earning->amount, 0.001);
    }

    /** §6.6 — a non-discounted order (no coupon, no credits) is unaffected. */
    public function test_guard_is_additive_only_no_discount_unchanged(): void
    {
        Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
        // A 0% coupon leaves the price exactly at retail.
        $q = app(CouponEngine::class)->price($this->coupon(0), 50.0, 10.0, 'esim', 40.0, null);
        $this->assertEqualsWithDelta(50.0, $q['price'], 0.001);
        $this->assertFalse($q['clamped']);
    }

    /** §6.3 — coupon + credits on the same order is rejected server-side, before any charge. */
    public function test_mutual_exclusivity_is_enforced_server_side(): void
    {
        Setting::setValue('credits.enabled', true, 'credits');
        \Illuminate\Support\Facades\Cache::flush();

        $plan = \App\Models\EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'mx-'.uniqid(), 'name' => 'USA 3GB 30D',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ])->fresh();
        $user = User::factory()->create();
        app(\App\Services\Wallet\WalletService::class)->credit($user, 50, 'USD');

        // Set credits ON first (which would clear a coupon via the UI hook), then
        // force a coupon too — simulating a crafted request with both present.
        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('useCredits', true)
            ->set('coupon', 'ANYTHING')
            ->call('purchase')
            ->assertSet('error', 'Choose either a coupon or NaaraCredits for this order — not both.');

        // Nothing was charged — the guard returns before any money math.
        $this->assertSame('50.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    private function makeMerchant(): Merchant
    {
        return Merchant::create([
            'owner_user_id' => User::factory()->create()->id,
            'business_name' => 'M', 'slug' => 'm-'.uniqid(), 'status' => Merchant::ACTIVE,
        ]);
    }
}
