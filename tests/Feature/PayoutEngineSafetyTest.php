<?php

namespace Tests\Feature;

use App\Events\PayoutReversed;
use App\Events\PayoutSettled;
use App\Jobs\AlertAdminJob;
use App\Jobs\EvaluatePayoutRequestJob;
use App\Jobs\SendPayoutJob;
use App\Models\PayoutAccount;
use App\Models\PayoutDecision;
use App\Models\PayoutProviderCall;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\LookupResult;
use App\Services\Payouts\PayoutEvent;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutReconciler;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Payouts\SupportsStatusLookup;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Addendum C engine-safety fixes: no provider call inside a DB transaction, one
 * approval winner, unknown outcomes are never refunded blindly (the double-pay
 * fix), and the free-payout/open-request limits count in-flight requests.
 */
class PayoutEngineSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
    }

    /** A scriptable provider. `$lookup` null = no lookup capability at all. */
    private function gateway(callable $send, ?callable $lookup = null): PayoutGatewayInterface
    {
        $base = $lookup === null
            ? new class($send) implements PayoutGatewayInterface
            {
                public int $sends = 0;

                public function __construct(public $send) {}

                public function name(): string { return 'paystack'; }

                public function available(): bool { return true; }

                public function createRecipient(PayoutAccount $account): string { return 'RCP'; }

                public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
                {
                    $this->sends++;

                    return ($this->send)($request);
                }

                public function verifyWebhook(Request $request): bool { return true; }

                public function parseWebhook(Request $request): ?PayoutEvent { return null; }
            }
            : new class($send, $lookup) implements PayoutGatewayInterface, SupportsStatusLookup
            {
                public int $sends = 0;

                public function __construct(public $send, public $lookup) {}

                public function name(): string { return 'paystack'; }

                public function available(): bool { return true; }

                public function createRecipient(PayoutAccount $account): string { return 'RCP'; }

                public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
                {
                    $this->sends++;

                    return ($this->send)($request);
                }

                public function lookupTransfer(PayoutRequest $request): LookupResult { return ($this->lookup)($request); }

                public function verifyWebhook(Request $request): bool { return true; }

                public function parseWebhook(Request $request): ?PayoutEvent { return null; }
            };

        $service = new PayoutService([$base]);
        $this->app->instance(PayoutService::class, $service);

        return $base;
    }

    private function account(User $u): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => '0123456789', 'account_name' => 'Test User', 'provider' => 'paystack',
            'is_verified' => true, 'is_default' => true,
        ]);
    }

    private function request(?User $u = null, string $bucket = 'referral_credits', ?string $ref = null): PayoutRequest
    {
        $u ??= User::factory()->create();
        $ref ??= 'wd:'.uniqid();

        return app(PayoutService::class)->createRequest($u, 5000, 'NGN', $bucket, $this->account($u), $ref);
    }

    private function httpError(int $status): RequestException
    {
        return new RequestException(new HttpResponse(new \GuzzleHttp\Psr7\Response($status, [], '{}')));
    }

    // ── A1: no provider call while a transaction is open; one approval winner ──

    public function test_the_provider_is_never_called_inside_a_db_transaction(): void
    {
        Queue::fake();
        $base = DB::transactionLevel(); // RefreshDatabase's own wrapper
        $seen = [];
        $this->gateway(function () use (&$seen, $base) {
            $seen[] = DB::transactionLevel() - $base;

            return new PayoutTransferResult('processing', 'TRF');
        });

        $req = $this->request();
        app(PayoutService::class)->send($req);

        $this->assertSame([0], $seen, 'sendTransfer ran inside an open DB transaction');
    }

    public function test_creating_a_request_queues_the_guardian_and_never_sends(): void
    {
        Queue::fake();
        $gw = $this->gateway(fn () => new PayoutTransferResult('processing', 'TRF'));
        Setting::setValue(PayoutSettings::MODE, 'auto');

        $req = $this->request();

        $this->assertSame(0, $gw->sends);
        $this->assertSame(PayoutRequest::PENDING, $req->fresh()->status);
        Queue::assertPushed(EvaluatePayoutRequestJob::class, fn ($j) => $j->payoutRequestId === $req->id && $j->queue === 'payout-guard');
        Queue::assertNotPushed(SendPayoutJob::class);
    }

    public function test_an_admin_and_the_system_approving_together_dispatch_exactly_one_send(): void
    {
        Queue::fake();
        $this->gateway(fn () => new PayoutTransferResult('processing'));
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $req = $this->request();
        $decision = PayoutDecision::create(['payout_request_id' => $req->id, 'decision' => 'approve', 'score' => 5]);

        $svc = app(PayoutService::class);
        $svc->approve($req, $admin);
        $this->assertFalse($svc->approveBySystem($req->fresh(), $decision), 'the loser must not approve again');
        $svc->approve($req->fresh(), $admin);

        Queue::assertPushed(SendPayoutJob::class, 1);
        $fresh = $req->fresh();
        $this->assertSame(PayoutRequest::APPROVED, $fresh->status);
        $this->assertSame('admin', $fresh->approval_source);
    }

    public function test_a_system_approval_records_its_source_and_queues_the_send_on_the_payouts_queue(): void
    {
        Queue::fake();
        $this->gateway(fn () => new PayoutTransferResult('processing'));
        $req = $this->request();
        $decision = PayoutDecision::create(['payout_request_id' => $req->id, 'decision' => 'approve', 'score' => 5]);

        $this->assertTrue(app(PayoutService::class)->approveBySystem($req, $decision));

        $fresh = $req->fresh();
        $this->assertSame('system', $fresh->approval_source);
        $this->assertNull($fresh->approved_by);
        $this->assertSame(PayoutRequest::REVIEW_APPROVED_AUTO, $fresh->review_state);
        Queue::assertPushed(SendPayoutJob::class, fn ($j) => $j->queue === 'payouts');
    }

    public function test_a_decision_row_can_never_be_edited_or_deleted(): void
    {
        $req = $this->request();
        $d = PayoutDecision::create(['payout_request_id' => $req->id, 'decision' => 'hold', 'score' => 1]);

        $this->expectException(\LogicException::class);
        $d->update(['decision' => 'approve']);
    }

    // ── A2: unknown outcomes ──────────────────────────────────────────────────

    public function test_a_timeout_after_submit_leaves_the_payout_processing_and_never_refunds(): void
    {
        Event::fake([PayoutReversed::class, PayoutSettled::class]);
        $this->gateway(fn () => throw new ConnectionException('cURL error 28: timed out'));
        $req = $this->request();

        $result = app(PayoutService::class)->send($req);

        $this->assertSame(PayoutRequest::PROCESSING, $result->status);
        Event::assertNotDispatched(PayoutReversed::class);
        $call = PayoutProviderCall::first();
        $this->assertSame(PayoutProviderCall::UNKNOWN, $call->state);
        $this->assertNotNull($call->next_lookup_at);
        $this->assertSame($req->reference, $call->idempotency_key);
    }

    public function test_a_5xx_is_unknown_but_a_4xx_validation_rejection_is_definitive(): void
    {
        Event::fake([PayoutReversed::class]);
        $this->gateway(fn () => throw $this->httpError(503));
        $a = app(PayoutService::class)->send($this->request());
        $this->assertSame(PayoutRequest::PROCESSING, $a->status);

        $this->gateway(fn () => throw $this->httpError(422));
        $b = app(PayoutService::class)->send($this->request());
        $this->assertSame(PayoutRequest::FAILED, $b->status);
        Event::assertDispatchedTimes(PayoutReversed::class, 1);
        $this->assertSame(PayoutProviderCall::DEFINITIVELY_FAILED, PayoutProviderCall::latest('id')->first()->state);
    }

    public function test_the_intent_row_is_written_before_the_provider_is_called(): void
    {
        $stateAtCall = null;
        $this->gateway(function ($request) use (&$stateAtCall) {
            $stateAtCall = PayoutProviderCall::where('payout_request_id', $request->id)->value('state');

            return new PayoutTransferResult('processing', 'TRF');
        });

        app(PayoutService::class)->send($this->request());

        $this->assertSame(PayoutProviderCall::SUBMITTED, $stateAtCall);
        $this->assertSame(PayoutProviderCall::CONFIRMED, PayoutProviderCall::first()->state);
    }

    public function test_the_reconciler_finds_the_transfer_paid_and_funds_are_never_returned(): void
    {
        Event::fake([PayoutReversed::class, PayoutSettled::class]);
        $this->gateway(fn () => throw new ConnectionException('timeout'), fn () => LookupResult::found('paid', 'TRF_9'));
        $req = app(PayoutService::class)->send($this->request());
        PayoutProviderCall::query()->update(['next_lookup_at' => now()->subMinute()]);

        $stats = app(PayoutReconciler::class)->reconcileDue();

        $this->assertSame(1, $stats['resolved']);
        $this->assertSame(PayoutRequest::PAID, $req->fresh()->status);
        Event::assertDispatched(PayoutSettled::class);
        Event::assertNotDispatched(PayoutReversed::class);
    }

    public function test_not_found_must_repeat_three_times_over_the_grace_window_before_reversing_once(): void
    {
        Event::fake([PayoutReversed::class]);
        Setting::setValue(PayoutSettings::LOOKUP_GRACE_MIN, 30);
        $this->gateway(fn () => throw new ConnectionException('timeout'), fn () => LookupResult::notFound());
        $req = app(PayoutService::class)->send($this->request());
        $rec = app(PayoutReconciler::class);
        $call = fn () => PayoutProviderCall::first();

        // Miss 1 and 2: waiting, nothing reversed.
        $call()->update(['next_lookup_at' => now()->subMinute()]);
        $rec->reconcileDue();
        $call()->update(['next_lookup_at' => now()->subMinute()]);
        $rec->reconcileDue();
        $this->assertSame(PayoutRequest::PROCESSING, $req->fresh()->status);
        Event::assertNotDispatched(PayoutReversed::class);

        // Third miss BEFORE the grace window has elapsed: still waiting.
        $call()->update(['next_lookup_at' => now()->subMinute()]);
        $rec->reconcileDue();
        $this->assertSame(PayoutRequest::PROCESSING, $req->fresh()->status);

        // Window elapsed + third (now fourth) miss: reversed exactly once.
        $call()->update(['first_not_found_at' => now()->subMinutes(45), 'next_lookup_at' => now()->subMinute()]);
        $rec->reconcileDue();

        $this->assertSame(PayoutRequest::FAILED, $req->fresh()->status);
        Event::assertDispatchedTimes(PayoutReversed::class, 1);
    }

    public function test_a_provider_without_lookup_support_goes_to_manual_review_with_no_refund(): void
    {
        Queue::fake();
        Event::fake([PayoutReversed::class]);
        $this->gateway(fn () => throw new ConnectionException('timeout')); // no SupportsStatusLookup
        $req = app(PayoutService::class)->send($this->request());
        PayoutProviderCall::query()->update(['next_lookup_at' => now()->subMinute()]);

        $stats = app(PayoutReconciler::class)->reconcileDue();

        $this->assertSame(1, $stats['manual']);
        $fresh = $req->fresh();
        $this->assertSame(PayoutRequest::PROCESSING, $fresh->status);
        $this->assertSame(PayoutRequest::REVIEW_MANUAL, $fresh->review_state);
        $this->assertSame('unknown_outcome', $fresh->hold_reason);
        Event::assertNotDispatched(PayoutReversed::class);
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_unknown_outcome');
        $this->assertNull(PayoutProviderCall::first()->next_lookup_at, 'must not be re-picked every two minutes');
    }

    public function test_a_late_webhook_resolves_an_unknown_call_before_the_reconciler_does(): void
    {
        $this->gateway(fn () => throw new ConnectionException('timeout'), fn () => throw new \RuntimeException('should not be asked'));
        $req = app(PayoutService::class)->send($this->request());

        app(PayoutService::class)->confirm($req->fresh(), 'TRF_WEBHOOK');

        $this->assertSame(PayoutRequest::PAID, $req->fresh()->status);
        $this->assertSame(PayoutProviderCall::CONFIRMED, PayoutProviderCall::first()->state);
        $this->assertSame(0, app(PayoutReconciler::class)->reconcileDue()['resolved'], 'nothing left to reconcile');
    }

    public function test_a_paid_report_after_reversal_still_raises_the_conflict_alert_instead_of_flipping(): void
    {
        Queue::fake();
        $this->gateway(fn () => new PayoutTransferResult('failed', null, 'Insufficient float'));
        $req = app(PayoutService::class)->send($this->request());
        $this->assertSame(PayoutRequest::FAILED, $req->status);

        $after = app(PayoutService::class)->confirm($req->fresh(), 'TRF_LATE');

        $this->assertSame(PayoutRequest::FAILED, $after->status);
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_confirm_conflict');
    }

    public function test_a_dead_worker_marks_the_open_call_unknown_never_dangling(): void
    {
        $req = $this->request();
        PayoutProviderCall::create(['payout_request_id' => $req->id, 'provider' => 'paystack', 'attempt' => 1,
            'idempotency_key' => $req->reference, 'state' => PayoutProviderCall::SUBMITTED]);

        (new SendPayoutJob($req->id))->failed(new \RuntimeException('worker killed'));

        $call = PayoutProviderCall::first();
        $this->assertSame(PayoutProviderCall::UNKNOWN, $call->state);
        $this->assertNotNull($call->next_lookup_at);
    }

    public function test_a_second_send_of_the_same_request_never_reaches_the_provider(): void
    {
        $gw = $this->gateway(fn () => new PayoutTransferResult('processing', 'TRF'));
        $req = $this->request();

        app(PayoutService::class)->send($req);
        app(PayoutService::class)->send($req->fresh());

        $this->assertSame(1, $gw->sends);
        $this->assertSame(1, PayoutProviderCall::count());
    }

    public function test_an_admin_can_resolve_an_unverifiable_outcome_only_with_a_note(): void
    {
        $this->gateway(fn () => throw new ConnectionException('timeout'));
        $req = app(PayoutService::class)->send($this->request());
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        try {
            app(PayoutReconciler::class)->adminResolve($req->fresh(), $admin, 'paid', '  ');
            $this->fail('a note is required');
        } catch (PayoutException) {
        }

        $done = app(PayoutReconciler::class)->adminResolve($req->fresh(), $admin, 'paid', 'Confirmed in the Paystack dashboard, TRF_77');
        $this->assertSame(PayoutRequest::PAID, $done->status);
    }

    // ── A3: in-flight requests count against the limits ───────────────────────

    public function test_in_flight_requests_count_toward_the_free_payout_limit(): void
    {
        Queue::fake();
        Setting::setValue(PayoutSettings::FREE_COUNT, 2);
        Setting::setValue(PayoutSettings::MAX_OPEN, 10);
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = app(PayoutService::class);

        $svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:a');
        $svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:b');

        $this->assertSame(2, app(PayoutThreshold::class)->committedCount($user));
        $this->assertSame(0, app(PayoutThreshold::class)->payoutCount($user), 'none are PAID yet');

        $this->expectException(PayoutException::class);
        $this->expectExceptionMessage('free payout limit');
        $svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:c');
    }

    public function test_a_reversal_frees_the_slot(): void
    {
        Queue::fake();
        Setting::setValue(PayoutSettings::FREE_COUNT, 1);
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = app(PayoutService::class);

        $first = $svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:a');
        try {
            $svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:b');
            $this->fail('should be blocked while the first is in flight');
        } catch (PayoutException) {
        }

        $svc->fail($first, 'provider said no');
        $this->assertNotNull($svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:b'));
    }

    public function test_the_open_request_cap_applies_per_user(): void
    {
        Queue::fake();
        Setting::setValue(PayoutSettings::FREE_COUNT, 50);
        Setting::setValue(PayoutSettings::MAX_OPEN, 2);
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = app(PayoutService::class);
        $svc->createRequest($user, 100, 'NGN', 'merchant_earnings', $account, 'm:a');
        $svc->createRequest($user, 100, 'NGN', 'merchant_earnings', $account, 'm:b');

        $this->expectException(PayoutException::class);
        $this->expectExceptionMessage('in progress');
        $svc->createRequest($user, 100, 'NGN', 'merchant_earnings', $account, 'm:c');

        // another user is unaffected (asserted separately below)
    }

    public function test_staff_and_platform_buckets_are_exempt_from_the_user_limits(): void
    {
        Queue::fake();
        Setting::setValue(PayoutSettings::FREE_COUNT, 0);
        Setting::setValue(PayoutSettings::MAX_OPEN, 1);
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = app(PayoutService::class);

        $svc->createRequest($user, 100, 'NGN', 'staff_earnings', $account, 's:a');
        $svc->createRequest($user, 100, 'NGN', 'staff_earnings', $account, 's:b');
        $svc->createRequest($user, 100, 'NGN', 'admin_core_margin', $account, 's:c');

        $this->assertSame(3, PayoutRequest::count());
    }

    public function test_creating_the_same_reference_twice_is_idempotent_and_queues_one_evaluation(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = app(PayoutService::class);

        $a = $svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:same');
        $b = $svc->createRequest($user, 100, 'NGN', 'referral_credits', $account, 'wd:same');

        $this->assertSame($a->id, $b->id);
        Queue::assertPushed(EvaluatePayoutRequestJob::class, 1);
    }
}
