<?php

namespace Tests\Feature;

use App\Exceptions\SpendCapExceededException;
use App\Livewire\Checkout;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletGroupService;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Prompt 11 §3 Batch 3 — paying for an eSIM from a shared plan instead of
 * your own wallet. Everything else in Checkout::purchase() (coupons,
 * credits, merchant margin, referral) is untouched by this feature — these
 * tests only exercise the part that changes: which wallet gets debited and
 * refunded, and that EsimOrder.user_id always stays the real buyer.
 */
class CheckoutSharedPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'shared-'.uniqid(), 'name' => 'USA 3GB 30D',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ])->fresh();
    }

    public function test_a_normal_purchase_with_no_group_selected_is_completely_unaffected(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->call('purchase')
            ->assertSet('done', true);

        $this->assertSame('10.0000', (string) $user->wallet->fresh()->usd_balance);
        $debit = WalletTransaction::where('user_id', $user->id)->where('type', 'debit')->first();
        $this->assertNull($debit->spent_by_user_id);
        $this->assertSame($user->id, EsimOrder::first()->user_id);
    }

    public function test_buying_via_an_accepted_shared_plan_debits_the_owners_wallet_not_the_buyers(): void
    {
        $plan = $this->plan();
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        app(WalletService::class)->credit($owner, 20, 'USD');
        $member = app(WalletGroupService::class)->invite($owner, $buyer, null, null);
        app(WalletGroupService::class)->accept($member);

        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        Livewire::actingAs($buyer)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('payFromGroupMemberId', $member->id)
            ->call('purchase')
            ->assertSet('done', true);

        // Owner's wallet debited; the buyer never had a wallet balance at all.
        $this->assertSame('10.0000', (string) $owner->wallet->fresh()->usd_balance);
        $debit = WalletTransaction::where('user_id', $owner->id)->where('type', 'debit')->first();
        $this->assertSame($buyer->id, $debit->spent_by_user_id);
        // EsimOrder.user_id is always the actual buyer, regardless of wallet source.
        $this->assertSame($buyer->id, EsimOrder::first()->user_id);
    }

    public function test_a_purchase_exceeding_the_spend_cap_is_blocked_before_any_debit(): void
    {
        $plan = $this->plan();
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        app(WalletService::class)->credit($owner, 20, 'USD');
        $member = app(WalletGroupService::class)->invite($owner, $buyer, capUsd: 5.0, capNgn: null);
        app(WalletGroupService::class)->accept($member);

        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        Livewire::actingAs($buyer)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('payFromGroupMemberId', $member->id)
            ->call('purchase')
            ->assertSet('done', false)
            ->assertSet('error', 'This purchase would exceed the spend cap set for you on that shared plan.');

        // Owner's wallet is completely untouched — the cap check ran BEFORE debit().
        $this->assertSame('20.0000', (string) $owner->wallet->fresh()->usd_balance);
        $this->assertSame(0, EsimOrder::count());
    }

    public function test_a_pending_not_yet_accepted_invite_cannot_be_used_to_pay(): void
    {
        $plan = $this->plan();
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        app(WalletService::class)->credit($owner, 20, 'USD');
        $member = app(WalletGroupService::class)->invite($owner, $buyer, null, null);
        // Deliberately NOT accepted.

        Livewire::actingAs($buyer)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('payFromGroupMemberId', $member->id)
            ->call('purchase')
            ->assertSet('done', false)
            ->assertSet('error', 'That shared plan is no longer available. Choose another payment source.');

        $this->assertSame('20.0000', (string) $owner->wallet->fresh()->usd_balance);
    }

    public function test_a_member_row_belonging_to_someone_else_cannot_be_used(): void
    {
        $plan = $this->plan();
        $owner = User::factory()->create();
        $realInvitee = User::factory()->create();
        $attacker = User::factory()->create();
        app(WalletService::class)->credit($owner, 20, 'USD');
        $member = app(WalletGroupService::class)->invite($owner, $realInvitee, null, null);
        app(WalletGroupService::class)->accept($member);

        Livewire::actingAs($attacker)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('payFromGroupMemberId', $member->id)
            ->call('purchase')
            ->assertSet('done', false)
            ->assertSet('error', 'That shared plan is no longer available. Choose another payment source.');

        $this->assertSame('20.0000', (string) $owner->wallet->fresh()->usd_balance);
    }

    public function test_a_provider_failure_refunds_the_owners_wallet_attributed_to_the_buyer(): void
    {
        $plan = $this->plan();
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        app(WalletService::class)->credit($owner, 20, 'USD');
        $member = app(WalletGroupService::class)->invite($owner, $buyer, null, null);
        app(WalletGroupService::class)->accept($member);

        // esimgo throws; the rest of the failover chain (airalo/quibity/zendit)
        // has no equivalent plan in this test's DB, so ProviderRouter skips
        // them via findEquivalentPlan() without ever touching their bindings,
        // and the whole chain is exhausted.
        app()->instance('esim.esimgo', new FakeEsimProvider(shouldThrow: true));

        Livewire::actingAs($buyer)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('payFromGroupMemberId', $member->id)
            ->call('purchase')
            ->assertSet('done', false);

        // The OWNER's wallet is back to its starting balance — refunded, not
        // the buyer's (who never had a wallet balance to begin with).
        $this->assertSame('20.0000', (string) $owner->wallet->fresh()->usd_balance);
        $refund = WalletTransaction::where('user_id', $owner->id)->where('type', 'refund')->first();
        $this->assertNotNull($refund, 'the refund must land on the owner wallet, attributed to the buyer');
        $this->assertSame($buyer->id, $refund->spent_by_user_id);
        // The cap-tracking ledger nets to zero again after the refund.
        $this->assertSame(0.0, app(WalletGroupService::class)->totalSpent($member->fresh(), 'USD'));
    }
}
