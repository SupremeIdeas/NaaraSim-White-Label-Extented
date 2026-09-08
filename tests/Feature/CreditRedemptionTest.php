<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Wallet\WalletService;
use App\Support\CreditSettings;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * NaaraCredit redemption at checkout (margin-capped) + the F1 refund-amount fix
 * (ProviderRouter refunds the ACTUAL amount charged, not list price).
 */
class CreditRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
        CreditSettings::flush();
    }

    private function plan(array $extra = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'cr-'.uniqid(), 'name' => 'USA 3GB 30D',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ], $extra))->fresh();
    }

    private function withCredits(User $user, float $credits): void
    {
        \App\Models\UserWallet::updateOrCreate(['user_id' => $user->id], ['naara_credits' => 0]);
        app(CreditService::class)->earn($user->fresh(), $credits, 'admin', 'seed:'.$user->id, 'seed');
    }

    public function test_redemption_is_margin_capped_never_below_cost_plus_profit(): void
    {
        // retail 10, cost 4 -> admin margin $6. The margin-safe floor (discount-
        // floor blueprint §2, 30% cap) now caps the discount at 30% of that
        // margin = $1.80, which binds BELOW the 50% price cap. A huge credit
        // balance still can't cross it.
        \App\Models\Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');
        \App\Models\Setting::setValue('credits.max_redeem_pct', 50, 'credits');
        \App\Models\Setting::setValue('credits.per_usd', 100, 'credits');
        CreditSettings::flush();

        $user = User::factory()->create();
        $this->withCredits($user, 100000); // way more than needed

        $q = app(CreditService::class)->quoteRedemption($user->fresh(), 10.00, 4.00);
        // Floor = 4 + 6*0.70 = 8.20; redeemable = 10 - 8.20 = 1.80 (30% of margin).
        $this->assertSame(1.8, $q['usd']);
        $this->assertSame(180.0, $q['credits']);

        // Raising the % price cap does nothing — the margin floor already binds.
        \App\Models\Setting::setValue('credits.max_redeem_pct', 100, 'credits');
        CreditSettings::flush();
        $q = app(CreditService::class)->quoteRedemption($user->fresh(), 10.00, 4.00);
        $this->assertSame(1.8, $q['usd']);
    }

    public function test_checkout_redeems_credits_and_charges_the_reduced_amount(): void
    {
        \App\Models\Setting::setValue('credits.max_redeem_pct', 50, 'credits');
        \App\Models\Setting::setValue('credits.first_purchase_bonus', 0, 'credits'); // isolate redemption
        CreditSettings::flush();
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        $this->withCredits($user, 500); // worth $5
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('useCredits', true)
            ->call('purchase')
            ->assertSet('done', true)
            ->assertDontSee('4.00'); // cost never shown

        // Margin-safe floor (§2): retail 10 / cost 4 → $6 margin → 30% = $1.80
        // redeemable. Wallet charged 10 - 1.80 = 8.20 (20 - 8.20 = 11.80 left);
        // 180 of the 500 credits spent, 320 remain.
        $this->assertSame('11.8000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame('320.00', (string) $user->wallet->fresh()->naara_credits);
        // The order records the REAL money collected, not list price.
        $this->assertSame('8.2000', (string) EsimOrder::firstOrFail()->price_charged);
    }

    public function test_provider_failure_refunds_both_wallet_and_credits_exactly(): void
    {
        \App\Models\Setting::setValue('credits.max_redeem_pct', 50, 'credits');
        CreditSettings::flush();
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        $this->withCredits($user, 500); // $5
        // eSIM Go throws — and no equivalent plan exists on airalo/quibity → total failure.
        app()->instance('esim.esimgo', new FakeEsimProvider(shouldThrow: true));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('useCredits', true)
            ->call('purchase')
            ->assertSet('done', false)
            ->assertSet('error', 'No provider could fulfil this plan right now — your wallet was refunded.');

        // F1: wallet refunded the ACTUAL $5 charged (not the $10 list) → back to 20.
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
        // Credits fully returned.
        $this->assertSame('500.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame(0, EsimOrder::count());
    }

    public function test_a_coupon_only_failure_refunds_the_discounted_amount_not_list(): void
    {
        // The pure F1 regression: coupon (no credits) + total provider failure
        // must refund the discounted price, not the full list price.
        \App\Models\Coupon::create(['code' => 'HALF', 'percent_off' => 40, 'applies_to' => 'all', 'per_user_limit' => 1, 'is_active' => true]);
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        app()->instance('esim.esimgo', new FakeEsimProvider(shouldThrow: true));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('coupon', 'HALF')
            ->call('applyCoupon')   // 40% off $10 = $6 charged
            ->call('purchase')
            ->assertSet('done', false);

        // Charged $6, refunded $6 → balance back to exactly 20 (not 24).
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
    }
}
