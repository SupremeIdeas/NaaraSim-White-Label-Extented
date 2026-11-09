<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TopupOrder;
use App\Models\TopupProduct;
use App\Models\TopupProvider;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\QuoteService;
use App\Services\Topup\TopupException;
use App\Services\Topup\TopupOps;
use App\Services\Topup\TopupOrderService;
use App\Services\Wallet\WalletService;
use App\Support\CreditSettings;
use App\Support\TopupSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeTopupProvider;
use Tests\TestCase;

/** Naara Credit discount on DATA top-ups: a small slice of OUR profit, worked from live cost and price, never below the minimum profit, refunded with the order. */
class TopupCreditDiscountTest extends TestCase
{
    use RefreshDatabase;

    private FakeTopupProvider $zendit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        config(['queue.default' => 'sync', 'services.zendit.api_key' => 'k']);
        $this->zendit = new FakeTopupProvider('zendit');
        $this->app->instance('topup.provider.zendit', $this->zendit);
        app(TopupOps::class)->seed();
        TopupProvider::query()->update(['enabled' => true]);
        TopupSettings::set('daily_cap_usd', 500);
        TopupSettings::set('new_account_daily_cap_usd', 500);
        Setting::setValue('features.naara_topup.enabled', true);
        Setting::setValue('credits.enabled', true);
        CreditSettings::flush();
        Cache::flush();
    }

    private function enable(array $o = []): void
    {
        foreach (['credit_discount_enabled' => true, 'credit_profit_share_pct' => 20, 'credit_max_discount_usd' => 0.5, 'credit_min_profit_usd' => 0.05, 'markup_pct' => 10, 'min_profit_usd' => 0.05] as $k => $v) {
            TopupSettings::set($k, $o[$k] ?? $v);
        }
    }

    private function product(string $type = 'data', float $cost = 2.0): TopupProduct
    {
        return TopupProduct::create(['provider_code' => 'zendit', 'provider_sku' => 'sku-'.uniqid(), 'country_iso' => 'NG', 'operator_name' => 'MTN', 'type' => $type,
            'name' => '2GB', 'currency' => 'NGN', 'is_fixed' => true, 'fixed_amount' => 3000, 'cost_native' => $cost, 'cost_currency' => 'USD', 'active' => true]);
    }

    private function user(float $usd = 100, float $credits = 5000): User
    {
        $u = User::factory()->create(['is_active' => true, 'created_at' => now()->subMonths(2)]);
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'seed:'.$u->id]);
        if ($credits > 0) {
            app(CreditService::class)->earn($u, $credits, 'test', 'seed-credits:'.$u->id);
        }

        return $u;
    }

    public function test_the_discount_is_off_by_default_and_never_touches_airtime(): void
    {
        $u = $this->user();
        $q = app(QuoteService::class)->create($u, $this->product('data'), '+2348031234567', 3000);
        $this->assertEquals(0, (float) $q->credit_usd, 'off until the admin switches it on');

        $this->enable();
        $air = app(QuoteService::class)->create($u, $this->product('airtime'), '+2348031234567', 3000);
        $this->assertEquals(0, (float) $air->credit_usd, 'airtime is never discounted');
    }

    public function test_the_discount_is_a_small_share_of_our_profit_and_never_breaks_the_floor(): void
    {
        $this->enable();
        $u = $this->user();
        $q = app(QuoteService::class)->create($u, $this->product('data', 2.0), '+2348031234567', 3000);

        $profit = (float) $q->list_price_usd - (float) $q->cost_usd;          // 10% markup on $2.00 = $0.20
        $this->assertEqualsWithDelta(0.20, $profit, 0.011);
        $this->assertGreaterThan(0, (float) $q->credit_usd);
        $this->assertLessThanOrEqual($profit * 0.20 + 0.0001, (float) $q->credit_usd, 'never more than 20% of our profit');
        $this->assertGreaterThanOrEqual(0.05, (float) $q->price_usd - (float) $q->cost_usd, 'minimum profit always remains');
        $this->assertEqualsWithDelta((float) $q->list_price_usd - (float) $q->credit_usd, (float) $q->price_usd, 0.0001);
    }

    public function test_a_thin_margin_gets_no_discount_and_a_fat_one_is_capped(): void
    {
        $this->enable(['markup_pct' => 1, 'min_profit_usd' => 0.05]);
        $u = $this->user();
        $thin = app(QuoteService::class)->create($u, $this->product('data', 2.0), '+2348031234567', 3000);
        $this->assertEquals(0, (float) $thin->credit_usd, 'profit $0.05 is already at the floor, so there is nothing to give');

        $this->enable(['markup_pct' => 60, 'credit_profit_share_pct' => 50, 'credit_max_discount_usd' => 0.25]);
        $fat = app(QuoteService::class)->create($u, $this->product('data', 10.0), '+2348031234567', 3000);
        $this->assertEqualsWithDelta(0.25, (float) $fat->credit_usd, 0.0001, 'the per-order cap wins');
    }

    public function test_it_is_limited_by_the_credit_balance_and_can_be_declined(): void
    {
        $this->enable();
        $poor = $this->user(100, 3);                       // 3 credits is a fraction of a cent
        $q = app(QuoteService::class)->create($poor, $this->product('data', 2.0), '+2348031234567', 3000);
        $this->assertLessThanOrEqual(CreditSettings::creditsToUsd(3) + 0.0001, (float) $q->credit_usd);

        $rich = $this->user(100, 5000);
        $no = app(QuoteService::class)->create($rich, $this->product('data', 2.0), '+2348031234567', 3000, useCredits: false);
        $this->assertEquals(0, (float) $no->credit_usd);
    }

    public function test_paying_spends_the_credits_with_the_wallet_and_a_failed_delivery_returns_both(): void
    {
        $this->enable();
        $u = $this->user(100, 5000);
        $credits0 = app(CreditService::class)->balance($u);
        $q = app(QuoteService::class)->create($u, $this->product('data', 2.0), '+2348031234567', 3000);
        $this->assertGreaterThan(0, (float) $q->credits);

        $this->zendit->purchaseScript = [new PurchaseResult(PurchaseResult::FAILED, null, definitive: true, message: 'no stock')];
        $o = app(TopupOrderService::class)->place($u, $q, 'k-credit');

        $this->assertSame(TopupOrder::REFUNDED, $o->fresh()->status);
        $this->assertEqualsWithDelta(100.0, (float) app(WalletService::class)->spendableUsd($u->fresh()), 0.001, 'wallet refunded in full');
        $this->assertEqualsWithDelta($credits0, app(CreditService::class)->balance($u->fresh()), 0.01, 'credits came back with the refund');
    }

    public function test_a_delivered_order_keeps_the_discount_visible_and_the_profit_floor_holds(): void
    {
        $this->enable();
        $u = $this->user(100, 5000);
        $q = app(QuoteService::class)->create($u, $this->product('data', 2.0), '+2348031234567', 3000);
        $o = app(TopupOrderService::class)->place($u, $q, 'k-ok');

        $this->assertSame(TopupOrder::DELIVERED, $o->fresh()->status);
        $this->assertEqualsWithDelta((float) $q->credit_usd, (float) $o->fresh()->credit_usd, 0.0001);
        $this->assertGreaterThanOrEqual(0.05, (float) $o->price_usd - (float) $o->cost_usd);
        $this->assertEqualsWithDelta(100 - (float) $o->price_usd, (float) app(WalletService::class)->spendableUsd($u->fresh()), 0.001);
    }

    public function test_a_tampered_quote_that_would_sell_below_the_floor_is_refused(): void
    {
        $this->enable();
        $u = $this->user(100, 5000);
        $q = app(QuoteService::class)->create($u, $this->product('data', 2.0), '+2348031234567', 3000);
        $q->forceFill(['price_usd' => (float) $q->cost_usd + 0.001])->save();

        $this->expectException(TopupException::class);
        app(TopupOrderService::class)->place($u, $q, 'k-bad');
    }
}
