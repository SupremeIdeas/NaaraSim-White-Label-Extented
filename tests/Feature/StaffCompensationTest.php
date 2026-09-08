<?php

namespace Tests\Feature;

use App\Livewire\PayoutDashboard;
use App\Models\OrderLog;
use App\Models\PayoutAccount;
use App\Models\Setting;
use App\Models\StaffCompensationProfile;
use App\Models\StaffEarning;
use App\Models\User;
use App\Services\Staff\StaffCompensationService;
use App\Services\Staff\StaffEarningsService;
use App\Services\Staff\StaffWithdrawalService;
use App\Support\PayoutSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA-BUILD-23 — staff profit-share. Shares from the SAME PlatformProfitService
 * figure partners do; accrues monthly, idempotently; staff are KYC-exempt.
 */
class StaffCompensationTest extends TestCase
{
    use RefreshDatabase;

    /** Book platform profit into the prior month via OrderLog (charged − cost). */
    private function seedPriorMonthProfit(float $profit): void
    {
        $log = OrderLog::create([
            'user_id' => User::factory()->create()->id,
            'naarasim_plan_id' => 1,
            'provider' => 'esimgo',
            'provider_cost' => 0,
            'charged_to_user' => $profit,
            'profit' => $profit,
            'result' => 'success',
        ]);
        // Eloquent stamps created_at = now() on insert; move it into the prior
        // month via the query builder so profitForPeriod() picks it up.
        $log->newQuery()->whereKey($log->id)->update([
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3),
        ]);
    }

    private function activeProfile(User $user, float $pct): StaffCompensationProfile
    {
        return StaffCompensationProfile::create([
            'user_id' => $user->id,
            'profit_share_pct' => $pct,
            'is_active' => true,
            'effective_from' => now()->subMonths(2)->toDateString(),
        ]);
    }

    public function test_monthly_close_computes_profit_once_and_applies_each_pct(): void
    {
        $this->seedPriorMonthProfit(1000.0);
        $a = $this->activeProfile(User::factory()->create(), 5);   // 5% → $50
        $b = $this->activeProfile(User::factory()->create(), 2.5); // 2.5% → $25

        $result = app(StaffCompensationService::class)->closeMonth();

        $this->assertEqualsWithDelta(1000.0, $result['profit'], 0.01);
        $this->assertSame(2, $result['credited']);
        $this->assertEqualsWithDelta(50.0, app(StaffEarningsService::class)->balance($a->user), 0.01);
        $this->assertEqualsWithDelta(25.0, app(StaffEarningsService::class)->balance($b->user), 0.01);
    }

    public function test_monthly_close_is_idempotent_against_a_double_run(): void
    {
        $this->seedPriorMonthProfit(1000.0);
        $a = $this->activeProfile(User::factory()->create(), 5);

        app(StaffCompensationService::class)->closeMonth();
        app(StaffCompensationService::class)->closeMonth(); // induced double-run

        $this->assertSame(1, StaffEarning::where('user_id', $a->user->id)->count());
        $this->assertEqualsWithDelta(50.0, app(StaffEarningsService::class)->balance($a->user), 0.01);
    }

    public function test_a_negative_profit_month_floors_each_share_at_zero(): void
    {
        $this->seedPriorMonthProfit(-500.0);
        $a = $this->activeProfile(User::factory()->create(), 5);

        $result = app(StaffCompensationService::class)->closeMonth();
        $this->assertSame(0, $result['credited']);
        $this->assertEqualsWithDelta(0.0, app(StaffEarningsService::class)->balance($a->user), 0.01);
    }

    public function test_a_rate_change_never_affects_an_earlier_period(): void
    {
        $this->seedPriorMonthProfit(1000.0);
        // effective_from is AFTER the period being closed → the rate doesn't apply.
        StaffCompensationProfile::create([
            'user_id' => User::factory()->create()->id,
            'profit_share_pct' => 50, 'is_active' => true,
            'effective_from' => now()->toDateString(), // this month, not the prior one
        ]);

        $result = app(StaffCompensationService::class)->closeMonth();
        $this->assertSame(0, $result['credited']); // not retroactive
    }

    public function test_combined_committed_pct_warns_over_100(): void
    {
        $this->activeProfile(User::factory()->create(), 60);
        $this->activeProfile(User::factory()->create(), 30);

        $svc = app(StaffCompensationService::class);
        // Existing 90% + a proposed new 20% = 110% → over budget.
        $this->assertEqualsWithDelta(110.0, $svc->combinedCommittedPct(20.0), 0.01);
    }

    public function test_staff_withdrawal_is_exempt_from_the_kyc_threshold(): void
    {
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        Setting::setValue(PayoutSettings::MIN, 1.0, 'payouts');
        Setting::setValue(PayoutSettings::FREE_COUNT, 0, 'payouts'); // 0 free → everyone else needs KYC

        $staff = User::factory()->create();
        app(StaffEarningsService::class)->accrue($staff, 40.0, 'seed', now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());
        $account = PayoutAccount::create([
            'user_id' => $staff->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'USD',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'Staff',
            'provider' => 'paystack', 'is_verified' => true,
        ]);

        // Free count is 0 (would block a referral earner) — staff still withdraw.
        $request = app(StaffWithdrawalService::class)->request($staff, $account, 40.0);
        $this->assertSame('staff_earnings', $request->source_bucket);
        $this->assertEqualsWithDelta(0.0, app(StaffEarningsService::class)->balance($staff), 0.01);
    }

    public function test_dashboard_renders_for_staff_with_no_limit_note(): void
    {
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $staff = User::factory()->create();

        Livewire::actingAs($staff)
            ->test(PayoutDashboard::class, ['earnerType' => 'staff'])
            ->assertOk()
            ->assertSee('no payout limits');
    }

    public function test_admin_can_set_a_rate_and_over_commitment_is_blocked(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        // Set a reasonable rate.
        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\Staff::class)
            ->set("comp.{$staff->id}", 20)
            ->call('saveCompensation', $staff->id)
            ->assertSet('compError', null);

        $this->assertEqualsWithDelta(20.0, (float) StaffCompensationProfile::where('user_id', $staff->id)->value('profit_share_pct'), 0.01);

        // Now a second staff at 90% would push the combined total to 110% → blocked.
        $staff2 = User::factory()->create();
        $staff2->assignRole('staff');
        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\Staff::class)
            ->set("comp.{$staff2->id}", 90)
            ->call('saveCompensation', $staff2->id)
            ->assertSeeHtml('over 100%');

        $this->assertSame(0, StaffCompensationProfile::where('user_id', $staff2->id)->count());
    }
}
