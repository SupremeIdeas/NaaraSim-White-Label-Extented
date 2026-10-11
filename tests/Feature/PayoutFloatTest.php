<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Jobs\SendPayoutJob;
use App\Models\PayoutAccount;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutFloatMovement;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\FloatService;
use App\Services\Payouts\PayoutEvent;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Payouts\ReportsBalance;
use App\Support\FinancialReconciliation;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Global Payout Layer Phase 4 (treasury) and Phase 5 (lean controls): float tracking,
 * `awaiting_funds`, FIFO resume, reconciliation mismatches, and the guards that keep
 * deposit-funded money from ever becoming withdrawable.
 */
class PayoutFloatTest extends TestCase
{
    use RefreshDatabase;

    private int $sent = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
    }

    private function gateway(?\Closure $send = null): void
    {
        $gw = new class($send ?? fn () => new PayoutTransferResult('processing', 'TRF_1'), $this) implements PayoutGatewayInterface
        {
            public function __construct(public $send, public $test) {}

            public function name(): string { return 'paystack'; }

            public function available(): bool { return true; }

            public function createRecipient(PayoutAccount $account): string { return 'RCP'; }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                $this->test->bump();

                return ($this->send)($request);
            }

            public function verifyWebhook(Request $request): bool { return true; }

            public function parseWebhook(Request $request): ?PayoutEvent { return null; }
        };
        $this->app->instance(PayoutService::class, new PayoutService([$gw]));
    }

    public function bump(): void
    {
        $this->sent++;
    }

    private function request(float $amount, ?User $u = null): PayoutRequest
    {
        $u ??= User::factory()->create();
        $acct = PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => (string) random_int(1000000000, 9999999999), 'account_name' => 'T', 'provider' => 'paystack',
            'is_verified' => true, 'is_default' => true,
        ]);
        Queue::fake();
        $r = app(PayoutService::class)->createRequest($u, $amount, 'NGN', 'staff_earnings', $acct, 'ref:'.uniqid());
        Queue::fake(); // forget the creation-time evaluation job

        return $r;
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    // ---- tracked vs untracked ----

    public function test_an_untracked_rail_is_not_gated_by_float(): void
    {
        $this->gateway();
        $r = app(PayoutService::class)->send($this->request(5000));

        $this->assertSame(PayoutRequest::PROCESSING, $r->status);
        $this->assertSame(1, $this->sent);
        $this->assertSame(0, PayoutFloatMovement::count());
    }

    public function test_a_funded_payout_draws_float_exactly_once(): void
    {
        $this->gateway();
        app(FloatService::class)->track('paystack', 'NGN', 10000);
        $r = $this->request(4000);

        app(PayoutService::class)->send($r);
        app(PayoutService::class)->send($r->refresh()); // a replayed job must not double-draw

        $this->assertEquals(6000, PayoutFloatBalance::first()->balance);
        $this->assertSame(1, PayoutFloatMovement::where('type', 'payout')->count());
        $this->assertSame(1, $this->sent);
    }

    // ---- awaiting_funds ----

    public function test_a_short_float_parks_the_request_without_calling_the_provider_or_losing_the_hold(): void
    {
        $this->gateway();
        app(FloatService::class)->track('paystack', 'NGN', 1000);
        $r = $this->request(4000);

        $result = app(PayoutService::class)->send($r);

        $this->assertSame(PayoutRequest::AWAITING_FUNDS, $result->status);
        $this->assertSame(0, $this->sent, 'no provider call');
        $this->assertFalse($result->isFinal());
        $this->assertEquals(1000, PayoutFloatBalance::first()->balance, 'float untouched');
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_awaiting_funds');
    }

    public function test_a_top_up_resumes_waiting_payouts_oldest_first_and_only_as_far_as_the_money_reaches(): void
    {
        $this->gateway();
        $float = app(FloatService::class);
        $float->track('paystack', 'NGN', 0);
        $a = app(PayoutService::class)->send($this->request(4000));
        $b = app(PayoutService::class)->send($this->request(3000));
        $c = app(PayoutService::class)->send($this->request(1000));
        $this->assertSame([PayoutRequest::AWAITING_FUNDS], array_unique([$a->status, $b->status, $c->status]));

        Queue::fake();
        $float->recordTopUp('paystack', 'NGN', 7500, $this->admin(), 'bank transfer ref 123');

        $this->assertSame(PayoutRequest::APPROVED, $a->refresh()->status);
        $this->assertSame(PayoutRequest::APPROVED, $b->refresh()->status);
        $this->assertSame(PayoutRequest::AWAITING_FUNDS, $c->refresh()->status, '7,500 covers 4,000 + 3,000 only');
        Queue::assertPushed(SendPayoutJob::class, 2);
    }

    public function test_a_younger_smaller_payout_cannot_jump_the_queue(): void
    {
        $this->gateway();
        $float = app(FloatService::class);
        $float->track('paystack', 'NGN', 0);
        $old = app(PayoutService::class)->send($this->request(5000));       // waits
        $float->recordTopUp('paystack', 'NGN', 2000, $this->admin(), 'partial'); // not enough for the old one
        $young = app(PayoutService::class)->send($this->request(500));      // 2,000 would cover it, but it is behind

        $this->assertSame(PayoutRequest::AWAITING_FUNDS, $old->refresh()->status);
        $this->assertSame(PayoutRequest::AWAITING_FUNDS, $young->refresh()->status);
    }

    public function test_float_retry_command_releases_without_a_new_top_up(): void
    {
        $this->gateway();
        $float = app(FloatService::class);
        $float->track('paystack', 'NGN', 0);
        $r = app(PayoutService::class)->send($this->request(1000));
        PayoutFloatBalance::query()->update(['balance' => 5000]); // e.g. provider sync raised it

        $this->artisan('payouts:float-retry')->expectsOutputToContain('released=1')->assertSuccessful();
        $this->assertSame(PayoutRequest::APPROVED, $r->refresh()->status);
    }

    public function test_an_admin_can_cancel_a_waiting_payout_and_the_hold_goes_back(): void
    {
        $this->gateway();
        app(FloatService::class)->track('paystack', 'NGN', 0);
        $r = app(PayoutService::class)->send($this->request(1000));

        app(PayoutService::class)->reject($r, $this->admin(), 'Declined by admin', 'user asked to cancel');

        $this->assertSame(PayoutRequest::REVERSED, $r->refresh()->status);
    }

    // ---- failure returns float ----

    public function test_a_definitive_failure_returns_the_float_and_an_unknown_outcome_keeps_it_drawn(): void
    {
        app(FloatService::class)->track('paystack', 'NGN', 10000);

        $this->gateway(fn () => new PayoutTransferResult('failed', null, 'invalid account'));
        $failed = app(PayoutService::class)->send($this->request(4000));
        $this->assertSame(PayoutRequest::FAILED, $failed->status);
        $this->assertEquals(10000, PayoutFloatBalance::first()->balance, 'float restored');
        $this->assertSame(1, PayoutFloatMovement::where('type', 'payout_reversal')->count());

        $this->gateway(fn () => throw new ConnectionException('timed out'));
        $unknown = app(PayoutService::class)->send($this->request(3000));
        $this->assertSame(PayoutRequest::PROCESSING, $unknown->status);
        $this->assertEquals(7000, PayoutFloatBalance::first()->balance, 'maybe-sent money stays drawn until a verified outcome');
    }

    public function test_reversing_the_debit_is_idempotent(): void
    {
        $this->gateway(fn () => new PayoutTransferResult('failed', null, 'nope'));
        $float = app(FloatService::class);
        $float->track('paystack', 'NGN', 5000);
        $r = app(PayoutService::class)->send($this->request(2000));

        $float->reverseDebit($r);
        $float->reverseDebit($r);

        $this->assertEquals(5000, PayoutFloatBalance::first()->balance);
    }

    // ---- ledger, alerts, adjustments ----

    public function test_movements_are_append_only_and_every_balance_equals_its_ledger(): void
    {
        $float = app(FloatService::class);
        $float->track('paystack', 'NGN', 100);
        $float->recordTopUp('paystack', 'NGN', 400, $this->admin(), 'x');
        $m = PayoutFloatMovement::first();

        $this->assertEquals(PayoutFloatBalance::first()->balance, PayoutFloatMovement::sum('amount'));
        $this->expectException(\LogicException::class);
        $m->update(['amount' => 1]);
    }

    public function test_low_balance_alerts_once_per_hour(): void
    {
        $this->gateway();
        app(FloatService::class)->track('paystack', 'NGN', 10000, 9000);
        [$a, $b] = [$this->request(2000), $this->request(1000)];
        Queue::fake();
        app(PayoutService::class)->send($a);
        app(PayoutService::class)->send($b);

        $alerts = Queue::pushed(AlertAdminJob::class)->filter(fn ($j) => $j->code === 'payout_float_low');
        $this->assertCount(1, $alerts);
    }

    public function test_adjustments_need_a_note_and_a_tracked_rail(): void
    {
        $float = app(FloatService::class);
        $admin = $this->admin();
        $float->track('paystack', 'NGN', 100);

        $float->adjust('paystack', 'NGN', -40, $admin, 'bank fee');
        $this->assertEquals(60, PayoutFloatBalance::first()->balance);

        $this->expectException(\App\Services\Payouts\PayoutException::class);
        $float->adjust('paystack', 'NGN', 10, $admin, '  ');
    }

    // ---- sync ----

    public function test_sync_aligns_tracked_float_with_the_providers_own_balance(): void
    {
        $float = app(FloatService::class);
        $row = $float->track('paystack', 'NGN', 1000);
        $movement = $float->syncTo($row, 1800.5);

        $this->assertSame('sync', $movement->type);
        $this->assertEquals(1800.5, $row->refresh()->balance);
        $this->assertNull($float->syncTo($row, 1800.5), 'no change, no movement');
    }

    public function test_paystack_balances_are_read_in_minor_units_and_a_failed_read_changes_nothing(): void
    {
        config(['services.paystack.secret_key' => 'sk_test', 'services.paystack.base_url' => 'https://api.paystack.test']);
        $row = app(FloatService::class)->track('paystack', 'NGN', 100);
        $row->forceFill(['auto_sync' => true])->save();

        Http::fake(['api.paystack.test/balance' => Http::response(['status' => true, 'data' => [['currency' => 'NGN', 'balance' => 250000]]])]);
        $this->artisan('payouts:float-sync')->expectsOutputToContain('synced=1')->assertSuccessful();
        $this->assertEquals(2500, $row->refresh()->balance);

        Http::fake(['api.paystack.test/balance' => Http::response('boom', 500)]);
        $this->artisan('payouts:float-sync')->assertSuccessful();
        $this->assertEquals(2500, $row->refresh()->balance, 'a failed read proves nothing');
        $this->assertInstanceOf(ReportsBalance::class, app(\App\Services\Payouts\PaystackPayoutGateway::class));
    }

    // ---- Guardian G9 on real float ----

    public function test_guardian_g9_defers_when_float_cannot_cover_the_request_plus_the_queue_ahead(): void
    {
        $float = app(FloatService::class);
        $float->track('paystack', 'NGN', 4500);
        $ahead = $this->request(4000);
        $ahead->forceFill(['status' => PayoutRequest::APPROVED])->save();
        $mine = $this->request(1000);

        $res = app(\App\Services\Payouts\Guardian\FloatFundingChecker::class)->check($mine);
        $this->assertSame('short', $res['status']);
        $this->assertSame(5000.0, $res['evidence']['needed_incl_queue_ahead']);

        $float->recordTopUp('paystack', 'NGN', 1000, $this->admin(), 'more');
        $this->assertSame('ok', app(\App\Services\Payouts\Guardian\FloatFundingChecker::class)->check($mine)['status']);
    }

    // ---- reconciliation ----

    public function test_reconciliation_reports_per_rail_figures_and_flags_a_seeded_mismatch(): void
    {
        $this->gateway();
        $float = app(FloatService::class);
        $float->track('paystack', 'NGN', 10000);
        $paid = app(PayoutService::class)->send($this->request(2000));
        $paid->forceFill(['status' => PayoutRequest::PAID])->save();

        $clean = collect(app(FinancialReconciliation::class)->payoutRails(now()->subDay(), now()->addDay()))->firstWhere('provider', 'paystack');
        $this->assertSame([], $clean['mismatches']);
        $this->assertEquals(2000, $clean['confirmed']);
        $this->assertEquals(8000, $clean['float_balance']);

        // Seed drift: someone edits the balance without a movement, and a live payout never drew float.
        PayoutFloatBalance::query()->update(['balance' => 9000]);
        $ghost = $this->request(500);
        $ghost->forceFill(['status' => PayoutRequest::PROCESSING])->save();

        $bad = collect(app(FinancialReconciliation::class)->payoutRails(now()->subDay(), now()->addDay()))->firstWhere('provider', 'paystack');
        $this->assertContains('float_ledger_drift', $bad['mismatches']);
        $this->assertContains('float_payout_drift', $bad['mismatches']);
        $this->assertArrayHasKey('payout_rails', app(FinancialReconciliation::class)->report(now()->subDay(), now()->addDay()));
    }

    // ---- Phase 5: lean controls ----

    public function test_the_deny_list_beats_an_enabled_corridor(): void
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        $r = $this->request(5000);
        $r->forceFill(['corridor_id' => \App\Models\PayoutCorridor::where('country', 'NG')->where('provider', 'paystack')->value('id')])->save();
        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, 'NG');
        Queue::fake();

        $d = app(\App\Services\Payouts\Guardian\PayoutGuardian::class)->evaluate($r);

        $this->assertSame(['hold', 'country_denied'], [$d->decision, $d->reason]);
    }

    public function test_a_sanctions_hit_goes_to_a_human(): void
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        $r = $this->request(5000);
        $this->app->bind(\App\Services\Payouts\Guardian\SanctionsScreening::class, fn () => new class implements \App\Services\Payouts\Guardian\SanctionsScreening
        {
            public function isHit(User $user, ?PayoutAccount $account): bool { return true; }
        });

        $this->assertSame('sanctions_hit', app(\App\Services\Payouts\Guardian\PayoutGuardian::class)->evaluate($r)->reason);
    }

    public function test_cap_edge_exactly_at_the_cap_is_allowed_one_cent_over_is_not(): void
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        Setting::setValue(PayoutSettings::USER_CAP_PREFIX.'daily', 20);
        $kyc = app(\App\Services\Kyc\KycService::class);
        $u = User::factory()->create(['name' => 'T']);
        $gate = new \App\Services\Payouts\Guardian\Gates\KycAndLimitsGate($kyc);

        $r = $this->request(100, $u);
        $r->forceFill(['source_bucket' => 'referral_earnings', 'usd_amount' => 20.00])->save();
        $this->assertFalse($gate->check(new \App\Services\Payouts\Guardian\GuardianContext($r->refresh()))->failed(), 'exactly at the cap');

        $r->forceFill(['usd_amount' => 20.01])->save();
        $res = $gate->check(new \App\Services\Payouts\Guardian\GuardianContext($r->refresh()));
        $this->assertSame(['cap_exceeded', 'reject'], [$res->reason, $res->outcome]);
    }

    public function test_deposit_funded_money_can_never_become_withdrawable(): void
    {
        $user = User::factory()->create();
        $wallet = app(\App\Services\Wallet\WalletService::class);
        $credits = app(\App\Services\Credits\CreditService::class);
        $before = $credits->withdrawableBalance($user);

        $wallet->creditTopUp($user, 50000, 'NGN', ['gateway' => 'paystack']);

        $this->assertSame($before, $credits->withdrawableBalance($user), 'a top-up never creates withdrawable credits');
        $this->assertSame(0.0, app(\App\Services\Referrals\ReferralEarningsService::class)->balance($user));
    }

    public function test_only_the_known_paths_can_create_withdrawable_credits(): void
    {
        // Static guard: if a new code path starts crediting the withdrawable bucket, this fails
        // and the compliance scope (no user-deposited funds are held for payout) must be re-opened.
        $allowed = [
            'app/Services/Credits/CreditService.php',      // rewardReferral() — platform-funded referral reward
            'app/Listeners/ReturnWithdrawnCredits.php',    // returns a withdrawal that failed (not new money)
        ];
        $hits = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'))) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (preg_match('/withdrawable:\s*true/', file_get_contents($file->getPathname()))) {
                $hits[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
        sort($hits);
        sort($allowed);
        $this->assertSame($allowed, $hits, 'a new path credits the withdrawable bucket — re-open the compliance scope first');
    }
}
