<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Livewire\GetNumber;
use App\Models\Coupon;
use App\Models\Setting;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\Credits\CreditService;
use App\Services\Wallet\WalletService;
use App\Support\CreditSettings;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * NaaraCredit redemption at the number checkout (mirrors CreditRedemptionTest
 * for eSIM). The number lane's retail is cents-level, so quoteRedemption()'s
 * 'sms' product must use pricing.sms_min_profit, not the eSIM $0.50 floor —
 * otherwise credits would never be redeemable here at all.
 */
class NumberCreditRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue('pricing.ngn_rate_source', 'manual', 'pricing');
        Setting::setValue('pricing.manual_ngn_rate', 1500, 'pricing');
        Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');
        Setting::setValue('pricing.sms_min_profit', 0.01, 'pricing');
        Setting::setValue('credits.max_redeem_pct', 50, 'credits');
        Setting::setValue('credits.first_purchase_bonus', 0, 'credits'); // isolate redemption
        CreditSettings::flush();
    }

    private function withCredits(User $user, float $credits): void
    {
        UserWallet::updateOrCreate(['user_id' => $user->id], ['naara_credits' => 0]);
        app(CreditService::class)->earn($user->fresh(), $credits, 'admin', 'seed:'.$user->id, 'seed');
    }

    /**
     * Bind fakes for EVERY provider in the nigeria/otp lane (fivesim, herosms,
     * virtsms) — the live BUILD-15 provider-ordering layer can reorder the lane
     * by health score, so leaving any lane member unbound risks a real network
     * call to an unconfigured provider regardless of which one "should" win.
     */
    private function fakeProvider(): void
    {
        app()->instance('number.fivesim', new FakeSmsProvider(
            price: 0.20,
            buyResponse: ['provider_ref' => '5S-1', 'number' => '2348010000000', 'cost' => 0.20, 'status' => 'pending'],
        ));
        app()->instance('number.herosms', new FakeSmsProvider(price: new OutOfStockException('n/a')));
        app()->instance('number.virtsms', new FakeSmsProvider(price: new OutOfStockException('n/a')));
    }

    public function test_number_redemption_is_margin_capped_using_the_sms_floor_not_the_esim_one(): void
    {
        // retail 0.31 / cost 0.20 -> $0.11 margin. eSIM's $0.50 absolute floor
        // would make this unredeemable (floor > retail); the 'sms' product must
        // use pricing.sms_min_profit (0.01) instead, giving a real quote.
        $user = User::factory()->create();
        $this->withCredits($user, 100000);

        $q = app(CreditService::class)->quoteRedemption($user->fresh(), 0.31, 0.20, product: 'sms');

        // Floor = max(0.20+0.01, 0.20+0.11*0.70) = max(0.21, 0.277) = 0.277.
        // maxByFloor = 0.31 - 0.277 = 0.033, rounded to cents by quoteRedemption -> 0.03.
        $this->assertSame(0.03, $q['usd']);
        $this->assertSame(3.0, $q['credits']);

        // The eSIM product (default) would floor at cost+0.50 > retail -> zero.
        $qEsim = app(CreditService::class)->quoteRedemption($user->fresh(), 0.31, 0.20);
        $this->assertSame(0.0, $qEsim['usd']);
    }

    public function test_number_order_redeems_credits_and_charges_the_reduced_amount(): void
    {
        $this->fakeProvider();
        Queue::fake();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        $this->withCredits($user, 500); // worth $5 — far more than the floor allows

        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('country', 'nigeria')
            ->set('service', 'whatsapp')
            ->set('useCredits', true)
            ->call('order')
            ->assertSet('error', null);

        // Wallet charged 0.31 - 0.03 = 0.28; only 3.0 of the 500 credits spent.
        $this->assertSame('19.7200', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame('497.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame('0.2800', (string) SmsOrder::firstOrFail()->charged_to_user);
    }

    public function test_number_order_provider_failure_refunds_wallet_and_credits_exactly(): void
    {
        // Every provider in the nigeria/otp lane must fail explicitly — leaving
        // any of them unbound would fall through to the REAL (unconfigured)
        // provider class and attempt a live network call.
        app()->instance('number.fivesim', new FakeSmsProvider(price: new OutOfStockException('n/a')));
        app()->instance('number.herosms', new FakeSmsProvider(price: new OutOfStockException('n/a')));
        app()->instance('number.virtsms', new FakeSmsProvider(price: new OutOfStockException('n/a')));
        Queue::fake();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        $this->withCredits($user, 500);

        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('country', 'nigeria')
            ->set('service', 'whatsapp')
            ->set('useCredits', true)
            ->call('order');

        // quote() itself fails when the provider throws, so nothing is charged
        // and no credits are spent — the cleanest possible failure.
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame('500.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame(0, SmsOrder::count());
    }

    public function test_number_order_mutual_exclusivity_is_enforced_server_side(): void
    {
        $this->fakeProvider();
        Queue::fake();
        Coupon::create(['code' => 'NUM10', 'percent_off' => 10, 'applies_to' => 'number', 'per_user_limit' => 1, 'is_active' => true]);
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        $this->withCredits($user, 500);

        // useCredits FIRST: its updated hook resets 'coupon' when turned on, so
        // setting the coupon afterward is the only order that actually leaves
        // both set by the time order() runs.
        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('country', 'nigeria')
            ->set('service', 'whatsapp')
            ->set('useCredits', true)
            ->set('coupon', 'NUM10')
            ->call('order')
            ->assertSet('error', 'Choose either a coupon or NaaraCredits for this order — not both.');

        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame('500.00', (string) $user->wallet->fresh()->naara_credits);
        $this->assertSame(0, SmsOrder::count());
    }

    public function test_a_plain_number_order_without_credits_still_works_unchanged(): void
    {
        $this->fakeProvider();
        Queue::fake();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('country', 'nigeria')
            ->set('service', 'whatsapp')
            ->call('order')
            ->assertSet('error', null);

        $this->assertSame('19.6900', (string) $user->wallet->fresh()->usd_balance); // 20 - 0.31
    }
}
