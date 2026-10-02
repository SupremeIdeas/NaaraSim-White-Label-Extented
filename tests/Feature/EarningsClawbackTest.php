<?php

namespace Tests\Feature;

use App\Livewire\Admin\PayoutHealth;
use App\Livewire\PayoutDashboard;
use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Payouts\Hardening\EarningsClawback;
use App\Services\Payouts\PayoutException;
use App\Services\Referrals\ReferralEarningsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Owner-approved clawback policy: reverse earnings by a person's decision; the earner may go into debt; repaid by future earnings. */
class EarningsClawbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function referral(float $earned = 10.0): array
    {
        $referrer = User::factory()->create();
        $buyer = User::factory()->create();
        $earn = app(ReferralEarningsService::class);
        $row = $earn->accrue($referrer, $buyer, 'esim', $earned, 'referral_reward:'.uniqid());

        return [$referrer, $buyer, $row, $earn];
    }

    public function test_a_clawback_reverses_the_accrual_once_and_can_leave_the_earner_in_debt(): void
    {
        [$referrer, $buyer, $row, $earn] = $this->referral(10.0);
        $earn->hold($referrer, 10.0, 'earn-hold:w1');                 // they already withdrew all of it
        $this->assertSame(0.0, $earn->balance($referrer));

        $earn->clawback($referrer, 10.0, 'clawback:'.$row->reference, 'Chargeback lost');
        $earn->clawback($referrer, 10.0, 'clawback:'.$row->reference, 'Chargeback lost');   // idempotent

        $this->assertSame(-10.0, $earn->balance($referrer));
        $this->assertSame(10.0, $earn->debt($referrer));
        $this->assertSame(1, ReferralEarning::where('type', 'clawback')->count());
        $this->assertStringContainsString('Chargeback lost', ReferralEarning::where('type', 'clawback')->first()->description);
    }

    public function test_while_in_debt_nothing_can_be_withdrawn_and_new_earnings_repay_it(): void
    {
        [$referrer, , , $earn] = $this->referral(10.0);
        $earn->hold($referrer, 10.0, 'earn-hold:w1');
        $earn->clawback($referrer, 10.0, 'clawback:x', 'Refund');
        $this->assertSame(-10.0, $earn->balance($referrer));

        try {
            $earn->hold($referrer, 1.0, 'earn-hold:w2');
            $this->fail('a hold on a negative balance must be refused');
        } catch (\RuntimeException) {
        }
        $this->assertSame(0.0, app(\App\Services\Referrals\ReferralWithdrawalService::class)->availableUsd($referrer), 'debt is never "available"');

        $earn->accrue($referrer, User::factory()->create(), 'esim', 4.0, 'referral_reward:new1');
        $this->assertSame(-6.0, $earn->balance($referrer), 'future earnings repay the debt first');
        $earn->accrue($referrer, User::factory()->create(), 'esim', 9.0, 'referral_reward:new2');
        $this->assertSame(3.0, $earn->balance($referrer));
        $this->assertSame(0.0, $earn->debt($referrer));
    }

    public function test_the_other_ledger_operations_still_refuse_to_overdraw(): void
    {
        [$referrer, , , $earn] = $this->referral(5.0);
        $this->expectException(\RuntimeException::class);
        $earn->hold($referrer, 6.0, 'earn-hold:too-much');
    }

    public function test_merchant_earnings_follow_the_same_policy(): void
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $owner->id, 'business_name' => 'Sahara Connect', 'slug' => 'sahara', 'status' => Merchant::ACTIVE]);
        $svc = app(MerchantEarningsService::class);
        $row = $svc->accrue($merchant, User::factory()->create(), 'esim', 10.0, 15.0, 'earn:order1');
        $svc->hold($merchant, 5.0, 'earn-hold:m1');

        $svc->clawback($merchant, (float) $row->amount, 'clawback:'.$row->reference, 'Refunded order');

        $this->assertSame(-5.0, $svc->balance($merchant));
        $this->assertSame(5.0, $svc->debt($merchant));
        $this->assertSame(0.0, app(\App\Services\Merchants\MerchantWithdrawalService::class)->availableUsd($merchant));
    }

    public function test_an_admin_finds_the_sale_and_reverses_it_with_a_reason_and_an_audit_trail(): void
    {
        [$referrer, $buyer, $row, $earn] = $this->referral(10.0);
        $svc = app(EarningsClawback::class);
        $found = $svc->candidates($buyer->email);

        $this->assertCount(1, $found);
        $this->assertFalse($found[0]['reversed']);

        try {
            $svc->reverse('referral', $row->id, $this->admin(), 'no');
            $this->fail('a real reason is required');
        } catch (PayoutException) {
        }

        $amount = $svc->reverse('referral', $row->id, $this->admin(), 'Card chargeback lost');

        $this->assertSame(10.0, $amount);
        $this->assertTrue($svc->candidates($buyer->email)[0]['reversed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'earnings.clawback']);
        $this->assertSame(0.0, $earn->balance($referrer));

        $svc->reverse('referral', $row->id, $this->admin(), 'Card chargeback lost');          // a second click is a no-op
        $this->assertSame(0.0, $earn->balance($referrer));
    }

    public function test_it_is_never_triggered_automatically_and_a_normal_user_cannot_use_it(): void
    {
        [, , $row] = $this->referral(10.0);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(EarningsClawback::class)->reverse('referral', $row->id, User::factory()->create(), 'I would like this reversed');
    }

    public function test_the_health_page_lets_finance_find_and_reverse_and_shows_the_result(): void
    {
        [$referrer, $buyer, $row] = $this->referral(8.0);

        Livewire::actingAs($this->admin())->test(PayoutHealth::class)
            ->set('clawQuery', $buyer->email)->call('findEarnings')->assertSee('Referrer #'.$referrer->id)
            ->set('clawReason', 'Refunded sale')->call('reverseEarnings', 'referral', $row->id)->assertSee('Reversed $8.00')
            ->assertSee('Already reversed');

        $this->assertSame(0.0, app(ReferralEarningsService::class)->balance($referrer));
    }

    public function test_the_earner_sees_an_adjustment_notice_not_a_negative_balance(): void
    {
        [$referrer, , , $earn] = $this->referral(10.0);
        $earn->hold($referrer, 10.0, 'earn-hold:w1');
        $earn->clawback($referrer, 10.0, 'clawback:x', 'Refunded sale');

        Livewire::actingAs($referrer)->test(PayoutDashboard::class, ['earnerType' => 'referral'])
            ->assertSee('An adjustment of $10.00')->assertDontSee('$-10.00')->assertSee('$0.00');
    }
}
