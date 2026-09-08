<?php

namespace Tests\Feature;

use App\Livewire\Admin\Reconciliation;
use App\Models\OrderLog;
use App\Models\PaymentCharge;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use App\Support\FinancialReconciliation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BUILD-5 §4 — the financial reconciliation view. Built on the wallet ledger +
 * gateway charges so an unaccounted-for gap (a silently-failed top-up webhook)
 * surfaces as a non-zero reconciliation gap.
 */
class FinancialReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_it_reports_money_in_paid_out_costs_and_a_balanced_gap(): void
    {
        $user = User::factory()->create();

        // Two gateway charges in → wallet credited the same amount = balanced.
        PaymentCharge::create(['gateway' => 'paystack', 'reference' => 'c1', 'amount' => 100, 'currency' => 'USD']);
        PaymentCharge::create(['gateway' => 'stripe', 'reference' => 'c2', 'amount' => 50, 'currency' => 'USD']);
        $this->credit($user, 150);

        OrderLog::create(['user_id' => $user->id, 'provider' => 'esimgo', 'provider_cost' => 3,
            'charged_to_user' => 9, 'profit' => 6, 'result' => 'success']);
        PayoutRequest::create(['user_id' => $user->id, 'amount' => 20, 'currency' => 'USD',
            'source_bucket' => 'referral_credits', 'status' => PayoutRequest::PAID, 'reference' => 'p1',
            'settled_at' => now()]);

        $report = app(FinancialReconciliation::class)->report(now()->subDays(30), now());

        $this->assertSame(150.0, $report['total_in']);
        $this->assertSame(['paystack' => 100.0, 'stripe' => 50.0], $report['in_by_gateway']);
        $this->assertSame(0.0, $report['reconciliation_gap']); // balanced
        $this->assertSame(20.0, $report['paid_out']);
        $this->assertSame(3.0, $report['provider_cost']);
        $this->assertSame(6.0, $report['gross_profit']);
    }

    public function test_a_charge_that_never_credited_the_wallet_shows_a_gap(): void
    {
        $user = User::factory()->create();
        PaymentCharge::create(['gateway' => 'paystack', 'reference' => 'c1', 'amount' => 100, 'currency' => 'USD']);
        $this->credit($user, 40); // only 40 of the 100 reached a wallet

        $report = app(FinancialReconciliation::class)->report(now()->subDays(30), now());

        $this->assertSame(60.0, $report['reconciliation_gap']); // 100 in − 40 credited
    }

    public function test_the_page_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Reconciliation::class)->assertStatus(403);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(Reconciliation::class)
            ->assertOk()
            ->assertSet('days', 30)
            ->call('setDays', 7)
            ->assertSet('days', 7);
    }

    private function credit(User $user, float $amount): void
    {
        UserWallet::firstOrCreate(['user_id' => $user->id]);
        WalletTransaction::create([
            'user_id' => $user->id, 'type' => 'credit', 'amount' => $amount, 'currency' => 'USD',
            'balance_before' => 0, 'balance_after' => $amount, 'reference' => 'topup:'.uniqid(),
        ]);
    }
}
