<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Merchants\MerchantException;
use App\Services\Merchants\MerchantWithdrawalService;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Wallet\WalletService;
use App\Support\MerchantSettings;
use App\Support\PayoutSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * ROADMAP §Layer 3.4 — merchant earnings ledger + settlement. The M−R upcharge
 * a reseller's customer pays is accrued to the merchant (only what's collected
 * above retail — never the admin's margin), and cashed out through the payout
 * engine with the same hold/reverse discipline as customer withdrawals.
 */
class MerchantEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue(MerchantSettings::MARGIN, 10.0, 'merchants');
    }

    private function merchant(): Merchant
    {
        return Merchant::create([
            'owner_user_id' => User::factory()->create(['is_active' => true])->id,
            'business_name' => 'Sahara Connect', 'slug' => 'sahara', 'status' => Merchant::ACTIVE,
        ]);
    }

    // ── Ledger service ──────────────────────────────────────────────────────

    public function test_accrual_records_only_the_upcharge_and_is_idempotent(): void
    {
        $m = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        $svc = app(MerchantEarningsService::class);

        // Charged 14.30 on a 13.00 retail → earns 1.30.
        $svc->accrue($m, $customer, 'esim', 13.00, 14.30, 'earn:ref-1');
        $svc->accrue($m, $customer, 'esim', 13.00, 14.30, 'earn:ref-1'); // replay

        $this->assertSame(1.30, $svc->balance($m));
        $this->assertDatabaseCount('merchant_earnings', 1);
    }

    public function test_no_upcharge_accrues_nothing(): void
    {
        $m = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        $svc = app(MerchantEarningsService::class);

        // A coupon pulled the charge back to (or below) retail — merchant earns 0.
        $this->assertNull($svc->accrue($m, $customer, 'esim', 13.00, 12.50, 'earn:ref-2'));
        $this->assertSame(0.0, $svc->balance($m));
    }

    public function test_hold_cannot_overdraw_and_release_returns(): void
    {
        $m = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        $svc = app(MerchantEarningsService::class);
        $svc->accrue($m, $customer, 'esim', 10.0, 15.0, 'earn:a'); // +5.00

        $svc->hold($m, 3.0, 'earn-hold:h1');
        $this->assertSame(2.0, $svc->balance($m));

        try {
            $svc->hold($m, 5.0, 'earn-hold:h2'); // only 2.00 left
            $this->fail('Expected MerchantException');
        } catch (MerchantException $e) {
            // expected
        }
        $this->assertSame(2.0, $svc->balance($m));

        $svc->release($m, 3.0, 'earn-release:h1');
        $this->assertSame(5.0, $svc->balance($m));
    }

    // ── Checkout accrual (integration) ──────────────────────────────────────

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'MP1',
            'name' => 'Roam 5GB', 'cost_price_usd' => 10.0, // retail 13, merchant 14.30
            'computed_retail_usd' => 13.00,
        ])->fresh();
    }

    public function test_a_merchant_customers_esim_purchase_charges_M_and_accrues_the_margin(): void
    {
        $m = $this->merchant();
        $plan = $this->plan();
        $customer = User::factory()->create(['is_active' => true, 'merchant_id' => $m->id]);
        app(WalletService::class)->credit($customer, 30, 'USD');
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        Livewire::actingAs($customer)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->call('purchase')
            ->assertSet('done', true);

        // Customer paid the merchant price 14.30 (wallet 30 → 15.70).
        $this->assertSame('15.7000', (string) $customer->wallet->fresh()->usd_balance);
        // Merchant earned 14.30 − 13.00 = 1.30.
        $this->assertSame(1.30, app(MerchantEarningsService::class)->balance($m));
    }

    public function test_a_non_merchant_customer_pays_plain_retail_and_accrues_nothing(): void
    {
        $plan = $this->plan();
        $customer = User::factory()->create(['is_active' => true]); // no merchant
        app(WalletService::class)->credit($customer, 30, 'USD');
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O2']));

        Livewire::actingAs($customer)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->call('purchase')
            ->assertSet('done', true);

        // Plain retail 13.00 (wallet 30 → 17.00).
        $this->assertSame('17.0000', (string) $customer->wallet->fresh()->usd_balance);
        $this->assertDatabaseCount('merchant_earnings', 0);
    }

    // ── Withdrawal + reversal ───────────────────────────────────────────────

    private function ownerAccount(Merchant $m): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $m->owner_user_id, 'type' => 'bank', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => '001', 'account_number' => '0123456789', 'account_name' => 'SAHARA LTD',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);
    }

    public function test_a_merchant_can_withdraw_earnings_which_holds_the_bucket(): void
    {
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $m = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        app(MerchantEarningsService::class)->accrue($m, $customer, 'esim', 10.0, 20.0, 'earn:x'); // +10
        $account = $this->ownerAccount($m);

        $request = app(MerchantWithdrawalService::class)->request($m, $account, 10.0);

        $this->assertSame('merchant_earnings', $request->source_bucket);
        $this->assertSame(0.0, app(MerchantEarningsService::class)->balance($m)); // held
    }

    public function test_withdrawing_more_than_the_balance_is_rejected(): void
    {
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $m = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        app(MerchantEarningsService::class)->accrue($m, $customer, 'esim', 10.0, 12.0, 'earn:y'); // +2
        $account = $this->ownerAccount($m);

        $this->expectException(\App\Services\Payouts\PayoutException::class);
        app(MerchantWithdrawalService::class)->request($m, $account, 50.0);
    }

    public function test_a_failed_transfer_returns_the_held_earnings(): void
    {
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $gateway = new class implements PayoutGatewayInterface
        {
            public function name(): string { return 'paystack'; }

            public function available(): bool { return true; }

            public function createRecipient(PayoutAccount $account): string { return 'RCP'; }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                return new PayoutTransferResult(status: 'failed', failureReason: 'Bank rejected');
            }

            public function verifyWebhook(\Illuminate\Http\Request $request): bool { return true; }

            public function parseWebhook(\Illuminate\Http\Request $request): ?\App\Services\Payouts\PayoutEvent { return null; }
        };
        $this->app->instance(PayoutService::class, new PayoutService([$gateway]));

        $m = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        app(MerchantEarningsService::class)->accrue($m, $customer, 'esim', 10.0, 20.0, 'earn:z'); // +10
        $account = $this->ownerAccount($m);

        $request = app(MerchantWithdrawalService::class)->request($m, $account, 10.0);
        $this->assertSame(0.0, app(MerchantEarningsService::class)->balance($m)); // held

        app(PayoutService::class)->send($request); // fails → PayoutReversed → earnings returned

        $this->assertSame(PayoutRequest::FAILED, $request->fresh()->status);
        $this->assertSame(10.0, app(MerchantEarningsService::class)->balance($m)); // returned
    }

    // ── Deferred verification: payout-size KYB threshold (BUILD-4 §1.3) ────────

    public function test_referral_margin_locks_at_signup_and_survives_later_changes(): void
    {
        // §3.3: a merchant-referred user is pinned to the margin-at-signup for the
        // life of the account, even if the merchant later changes it.
        $m = Merchant::create([
            'owner_user_id' => User::factory()->create(['is_active' => true])->id,
            'business_name' => 'Lock Co', 'slug' => 'lock', 'status' => Merchant::ACTIVE,
            'reseller_margin_pct' => 10.0,
        ]);
        $plan = $this->plan(); // retail 13.00

        // Join via the invite → margin snapshotted at 10%.
        \App\Support\MerchantBranding::captureInvite($m->slug);
        $user = User::factory()->create(['is_active' => true]);
        \App\Support\MerchantBranding::consumeInviteFor($user);
        $this->assertSame('10.000', (string) $user->fresh()->merchant_margin_pct);

        // Merchant later raises its margin to 30%.
        $m->forceFill(['reseller_margin_pct' => 30.0])->save();

        $engine = app(\App\Services\Pricing\PricingEngine::class);
        $locked = $engine->merchantEsimPrice($plan, $m->fresh(), false, (float) $user->fresh()->merchant_margin_pct);
        $live = $engine->merchantEsimPrice($plan, $m->fresh(), false); // 30% now

        $this->assertSame(14.30, $locked);       // 13.00 × 1.10 — locked at signup
        $this->assertLessThan($live, $locked);    // cheaper than the merchant's new 30%
    }

    public function test_a_large_payout_clears_without_kyb_when_the_rule_is_off(): void
    {
        // The KYB-above-threshold rule ships OFF: a $600 payout (over the $500
        // default) clears on the verified (KYC-L2) account alone.
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $m = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        app(MerchantEarningsService::class)->accrue($m, $customer, 'esim', 100.0, 700.0, 'earn:big'); // +600
        $account = $this->ownerAccount($m);

        $request = app(MerchantWithdrawalService::class)->request($m, $account, 600.0);

        $this->assertSame('merchant_earnings', $request->source_bucket);
    }

    public function test_a_large_payout_requires_kyb_when_the_rule_is_on(): void
    {
        // Switch the rule on: over the threshold, an unverified (no L3) owner is
        // blocked until they complete business KYB.
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        Setting::setValue(MerchantSettings::KYB_OVER_THRESHOLD, true, 'merchants');
        $m = $this->merchant(); // owner has no L3
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        app(MerchantEarningsService::class)->accrue($m, $customer, 'esim', 100.0, 700.0, 'earn:big2'); // +600
        $account = $this->ownerAccount($m);

        $this->expectException(\App\Services\Payouts\PayoutException::class);
        app(MerchantWithdrawalService::class)->request($m, $account, 600.0); // > $500, no KYB
    }
}
