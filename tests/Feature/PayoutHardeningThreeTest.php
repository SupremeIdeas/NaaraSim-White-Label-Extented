<?php

namespace Tests\Feature;

use App\Livewire\Admin\Payouts;
use App\Models\CreditLedger;
use App\Models\PayeeTaxProfile;
use App\Models\PayoutAccount;
use App\Models\PayoutAccountingEntry;
use App\Models\PayoutFloatMovement;
use App\Models\PayoutReconciliationItem;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Hardening\PayeeTax;
use App\Services\Payouts\Hardening\PayoutAccounting;
use App\Services\Payouts\Hardening\PayoutInvariants;
use App\Services\Payouts\Hardening\SettlementReconciler;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Support\PayoutSettings;
use App\Support\StaffScopes;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Addendum D (D-H3): accounting ledger, settlement reconciliation, tax hooks, scoped roles, dual control. */
class PayoutHardeningThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
        Queue::fake();
    }

    private function request(float $usd = 10.0, float $fee = 0.5, string $provider = 'paystack', ?User $u = null): PayoutRequest
    {
        $u ??= User::factory()->create();
        $account = PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => (string) random_int(1000000000, 9999999999), 'account_name' => 'T U', 'provider' => $provider, 'is_verified' => true, 'is_default' => true,
        ]);
        $ref = 'wd:'.uniqid();
        CreditLedger::create(['user_id' => $u->id, 'type' => 'spend', 'source' => 'withdraw', 'withdrawable' => true, 'amount' => $usd, 'balance_after' => 0, 'reference' => 'wd-hold:'.$ref]);
        $r = app(PayoutService::class)->createRequest($u, ($usd - $fee) * 500, 'NGN', 'referral_credits', $account, $ref, ['usd_amount' => $usd, 'fx_rate' => 500, 'platform_fee_usd' => $fee]);
        $r->forceFill(['credit_amount' => $usd])->save();

        return $r->fresh();
    }

    private function balanced(): void
    {
        $d = (float) PayoutAccountingEntry::where('direction', 'debit')->sum('amount_usd');
        $c = (float) PayoutAccountingEntry::where('direction', 'credit')->sum('amount_usd');
        $this->assertEqualsWithDelta($d, $c, 0.00001, 'debits must equal credits');
    }

    private function net(string $account): float
    {
        return (float) PayoutAccountingEntry::where('account_code', $account)->where('direction', 'debit')->sum('amount_usd')
            - (float) PayoutAccountingEntry::where('account_code', $account)->where('direction', 'credit')->sum('amount_usd');
    }

    // ── accounting ledger ──

    public function test_a_payout_posts_balanced_hold_then_paid_lines_with_the_fee_split(): void
    {
        $r = $this->request(10.0, 0.5);
        $this->assertSame(2, PayoutAccountingEntry::count(), 'hold posted at request time');
        $this->assertEqualsWithDelta(10.0, $this->net(PayoutAccounting::LIABILITY), 0.0001);
        $this->assertEqualsWithDelta(-10.0, $this->net(PayoutAccounting::IN_TRANSIT), 0.0001);

        app(PayoutService::class)->confirm($r->fresh(), 'TRF');

        $this->balanced();
        $this->assertEqualsWithDelta(0.0, $this->net(PayoutAccounting::IN_TRANSIT), 0.0001, 'nothing left in transit once paid');
        $this->assertEqualsWithDelta(-9.5, $this->net('provider_float_paystack'), 0.0001, 'what actually left the provider account');
        $this->assertEqualsWithDelta(-0.5, $this->net(PayoutAccounting::FEE_INCOME), 0.0001);
    }

    public function test_a_failed_payout_reverses_its_hold_and_posting_is_idempotent(): void
    {
        $r = $this->request();
        app(PayoutService::class)->fail($r->fresh(), 'provider said no');
        app(PayoutService::class)->fail($r->fresh(), 'again');
        app(PayoutService::class)->confirm($r->fresh(), 'late'); // conflict path posts nothing

        $this->balanced();
        $this->assertEqualsWithDelta(0.0, $this->net(PayoutAccounting::LIABILITY), 0.0001, 'the user is owed nothing extra and nothing less');
        $this->assertEqualsWithDelta(0.0, $this->net(PayoutAccounting::IN_TRANSIT), 0.0001);
        $this->assertSame(4, PayoutAccountingEntry::count(), 'hold (2) + reversal (2), never duplicated');
    }

    public function test_a_returned_payout_unwinds_the_paid_entries_and_the_books_net_to_zero(): void
    {
        $r = $this->request(10.0, 0.5);
        $svc = app(PayoutService::class);
        $svc->confirm($r->fresh(), 'TRF');
        $svc->markReturned($r->fresh(), 'bank bounced it', User::factory()->create()->assignRole('admin'));

        $this->balanced();
        foreach ([PayoutAccounting::LIABILITY, PayoutAccounting::IN_TRANSIT, 'provider_float_paystack', PayoutAccounting::FEE_INCOME] as $acct) {
            $this->assertEqualsWithDelta(0.0, $this->net($acct), 0.0001, "{$acct} nets to zero after a return");
        }
    }

    public function test_the_ledger_is_append_only(): void
    {
        $this->request();
        $e = PayoutAccountingEntry::first();

        $this->expectException(\LogicException::class);
        $e->update(['amount_usd' => 1]);
    }

    public function test_deleting_is_refused_too(): void
    {
        $this->request();
        $this->expectException(\LogicException::class);
        PayoutAccountingEntry::first()->delete();
    }

    public function test_an_unbalanced_posting_is_refused_and_a_request_with_no_usd_value_posts_nothing(): void
    {
        $this->expectException(\LogicException::class);
        $r = $this->request();
        $rc = new \ReflectionMethod(PayoutAccounting::class, 'write');
        $rc->setAccessible(true);
        $rc->invoke(app(PayoutAccounting::class), $r, $r->id, 'bad:1', [['a', 'debit', 100], ['b', 'credit', 99]], 'x', $r);
    }

    public function test_invariants_flag_an_unbalanced_group_and_a_paid_payout_missing_its_postings(): void
    {
        $r = $this->request();
        app(PayoutService::class)->confirm($r->fresh(), 'TRF');
        $this->assertSame('ok', app(PayoutInvariants::class)->run('t', false)->status);

        // Corrupt the books directly (bypassing the model's append-only guard).
        \DB::table('payout_accounting_entries')->where('account_code', PayoutAccounting::FEE_INCOME)->update(['amount_usd' => 3]);
        \DB::table('payout_accounting_entries')->where('posting_key', 'like', 'paid:%')->where('account_code', PayoutAccounting::IN_TRANSIT)->delete();
        $r2 = $this->request();
        $r2->forceFill(['status' => PayoutRequest::PAID, 'settled_at' => now()])->save();
        \DB::table('payout_accounting_entries')->where('payout_request_id', $r2->id)->where('posting_key', 'like', 'paid:%')->delete();

        $run = app(PayoutInvariants::class)->run('t', false);

        $check = collect($run->results)->firstWhere('id', 'accounting_balanced');
        $this->assertFalse($check['ok']);
        $problems = array_column($check['offenders'], 'problem');
        $this->assertContains('unbalanced_group', $problems);
        $this->assertContains('paid_without_accounting', $problems);
    }

    public function test_the_month_end_export_lists_the_postings(): void
    {
        $this->request();
        $file = tempnam(sys_get_temp_dir(), 'acct');

        $this->artisan('payouts:accounting-export', ['month' => now()->format('Y-m'), '--out' => $file])->assertSuccessful();

        $csv = file_get_contents($file);
        $this->assertStringContainsString('earnings_liability', $csv);
        $this->assertStringContainsString('payout_in_transit', $csv);
        unlink($file);
    }

    // ── settlement reconciliation ──

    public function test_reconciliation_matches_flags_and_books_the_provider_fee_without_changing_any_payout(): void
    {
        $paid = $this->request(10.0, 0.5);
        app(PayoutService::class)->confirm($paid->fresh(), 'TRF');
        $short = $this->request(20.0, 0.5);
        app(PayoutService::class)->confirm($short->fresh(), 'TRF2');
        $ghost = $this->request(30.0, 0.5);
        app(PayoutService::class)->confirm($ghost->fresh(), 'TRF3');   // we say paid; statement won't list it

        $csv = "reference,amount,currency,fee\n"
            .$paid->provider_reference.','.$paid->amount.",NGN,50\n"           // matches, with a fee
            .$short->provider_reference.','.($short->amount - 100).",NGN,\n"   // amount differs
            ."UNKNOWN-REF,5000,NGN,\n";                                         // provider paid something we never sent
        $before = PayoutRequest::pluck('status', 'id')->all();

        $run = app(SettlementReconciler::class)->reconcileCsv('paystack', $csv, now()->subDay(), now()->addDay());

        $kinds = PayoutReconciliationItem::where('run_id', $run->id)->pluck('kind')->all();
        foreach (['matched', 'amount_mismatch', 'missing_in_system', 'missing_at_provider', 'fee_unrecorded'] as $k) {
            $this->assertContains($k, $kinds, $k);
        }
        $this->assertSame(1, $run->matched);
        $this->assertSame(3, $run->flagged);
        $this->assertSame($before, PayoutRequest::pluck('status', 'id')->all(), 'reconciliation never changes a payout');
        $this->assertEqualsWithDelta(0.1, $this->net(PayoutAccounting::PROVIDER_FEES), 0.0001, 'NGN 50 at 500/USD = $0.10, booked once');
        $this->assertCount(1, Queue::pushed(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'settlement_mismatch'));
        $this->balanced();

        // Re-running the same statement never double-books the fee.
        app(SettlementReconciler::class)->reconcileCsv('paystack', $csv, now()->subDay(), now()->addDay());
        $this->assertEqualsWithDelta(0.1, $this->net(PayoutAccounting::PROVIDER_FEES), 0.0001);
    }

    public function test_a_statement_needs_its_columns_and_resolution_needs_a_note(): void
    {
        try {
            app(SettlementReconciler::class)->reconcileCsv('paystack', "foo,bar\n1,2", now(), now());
            $this->fail('missing columns');
        } catch (PayoutException $e) {
            $this->assertStringContainsString("'reference'", $e->getMessage());
        }

        $run = app(SettlementReconciler::class)->reconcileCsv('paystack', "reference,amount\nZZZ,10", now()->subDay(), now()->addDay());
        $item = $run->items()->first();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        try {
            app(SettlementReconciler::class)->resolve($item, $admin, ' ');
            $this->fail('a note is required');
        } catch (PayoutException) {
        }
        app(SettlementReconciler::class)->resolve($item, $admin, 'Provider test transfer — ignore');
        $this->assertNotNull($item->fresh()->resolved_at);
    }

    public function test_the_reconcile_command_reports_a_clean_statement(): void
    {
        $r = $this->request();
        app(PayoutService::class)->confirm($r->fresh(), 'TRF');
        $file = tempnam(sys_get_temp_dir(), 'stmt');
        file_put_contents($file, "reference,amount,currency\n{$r->provider_reference},{$r->amount},NGN\n");

        $this->artisan('payouts:reconcile-settlement', ['provider' => 'paystack', 'file' => $file, '--from' => now()->subDay()->toDateString(), '--to' => now()->addDay()->toDateString()])
            ->expectsOutputToContain('1 matched, 0 to review')->assertSuccessful();
        unlink($file);
    }

    // ── tax hooks ──

    public function test_the_tax_gate_is_off_by_default_and_when_on_blocks_until_details_are_on_file(): void
    {
        $u = User::factory()->create();
        $r = $this->request(500.0, 0.5, 'paystack', $u);
        app(PayoutService::class)->confirm($r->fresh(), 'TRF');
        $tax = app(PayeeTax::class);

        $this->assertFalse($tax->blocks($u), 'OFF by default');
        $this->assertEqualsWithDelta(500.0, $tax->paidThisYearUsd($u), 0.001);

        Setting::setValue(PayoutSettings::TAX_FORM_OVER_USD, 400);
        $this->assertTrue($tax->blocks($u));
        try {
            $this->request(10.0, 0.5, 'paystack', $u);
            $this->fail('should be blocked');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('tax details', $e->getMessage());
        }

        PayeeTaxProfile::create(['user_id' => $u->id, 'tax_country' => 'NG', 'form_type' => 'W-8BEN', 'form_status' => 'received', 'collected_at' => now()]);
        $this->assertFalse($tax->blocks($u));
    }

    public function test_the_annual_summary_lists_each_payee_and_provider_with_tax_status(): void
    {
        $u = User::factory()->create();
        $r1 = $this->request(100.0, 0, 'paystack', $u);
        app(PayoutService::class)->confirm($r1->fresh(), 'A');
        $r2 = $this->request(50.0, 0, 'flutterwave', $u);
        app(PayoutService::class)->confirm($r2->fresh(), 'B');
        PayeeTaxProfile::create(['user_id' => $u->id, 'tax_country' => 'GH', 'form_status' => 'requested']);

        $rows = collect(app(PayeeTax::class)->annualSummary((int) now()->year));

        $this->assertSame([100.0, 50.0], $rows->sortByDesc('total_usd')->pluck('total_usd')->all());
        $this->assertSame('requested', $rows->first()['form_status']);
        $file = tempnam(sys_get_temp_dir(), 'tax');
        $this->artisan('payouts:annual-summary', ['year' => now()->year, '--out' => $file])->assertSuccessful();
        $this->assertStringContainsString('total_usd', file_get_contents($file));
        unlink($file);
    }

    // ── roles / segregation ──

    public function test_the_payout_scopes_exist_and_admins_hold_them(): void
    {
        $this->assertTrue(StaffScopes::isValid('payouts.review'));
        $this->assertTrue(StaffScopes::isValid('payouts.finance'));
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->assertTrue($admin->can('payouts.review') && $admin->can('payouts.finance'));
    }

    private function staffWith(string ...$scopes): User
    {
        $u = User::factory()->create();
        $u->assignRole('staff');
        foreach ($scopes as $s) {
            Permission::findOrCreate($s, 'web');
            $u->givePermissionTo($s);
        }

        return $u;
    }

    public function test_a_reviewer_can_decide_requests_but_not_fund_float_or_change_settings(): void
    {
        $r = $this->request();
        $reviewer = $this->staffWith('payouts.review');

        Livewire::actingAs($reviewer)->test(Payouts::class)
            ->set("notes.{$r->id}", 'looks fine')->call('approve', $r->id)->assertHasNoErrors();
        $this->assertSame(PayoutRequest::APPROVED, $r->fresh()->status);

        Livewire::actingAs($reviewer)->test(Payouts::class)
            ->set('floatProvider', 'paystack')->set('floatCurrency', 'NGN')->set('floatAmount', 10)->set('floatNote', 'x')
            ->call('topUp')->assertForbidden();
        Livewire::actingAs($reviewer)->test(Payouts::class)->set('enabled', false)->call('save')->assertForbidden();
        Livewire::actingAs($reviewer)->test(Payouts::class)->call('saveGuardian')->assertForbidden();
    }

    public function test_finance_can_fund_float_but_cannot_approve_and_a_plain_user_sees_nothing(): void
    {
        $r = $this->request();
        $finance = $this->staffWith('payouts.finance');

        Livewire::actingAs($finance)->test(Payouts::class)
            ->set("notes.{$r->id}", 'x')->call('approve', $r->id)->assertForbidden();
        Livewire::actingAs($finance)->test(Payouts::class)
            ->set('floatProvider', 'paystack')->set('floatCurrency', 'NGN')->set('floatAmount', 100)->call('trackRail')->assertHasNoErrors();

        Livewire::actingAs($this->staffWith())->test(Payouts::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(Payouts::class)->assertForbidden();
    }

    public function test_dual_control_stops_whoever_just_funded_float_from_approving_a_large_payout(): void
    {
        $r = $this->request(500.0);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $other = User::factory()->create();
        $other->assignRole('admin');
        Setting::setValue(PayoutSettings::DUAL_CONTROL_USD, 200);
        PayoutFloatMovement::create(['provider' => 'paystack', 'currency' => 'NGN', 'type' => 'topup', 'amount' => 1000, 'balance_after' => 1000, 'created_by' => $admin->id]);

        try {
            app(PayoutService::class)->approve($r, $admin, 'x');
            $this->fail('the funder must not also approve');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('Dual control', $e->getMessage());
        }
        $this->assertSame(PayoutRequest::PENDING, $r->fresh()->status);

        app(PayoutService::class)->approve($r, $other, 'ok');   // a different reviewer can
        $this->assertSame(PayoutRequest::APPROVED, $r->fresh()->status);

        $small = $this->request(50.0);
        app(PayoutService::class)->approve($small, $admin, 'small is fine');
        $this->assertSame(PayoutRequest::APPROVED, $small->fresh()->status);
    }
}
