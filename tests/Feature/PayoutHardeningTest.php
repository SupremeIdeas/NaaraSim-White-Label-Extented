<?php

namespace Tests\Feature;

use App\Events\PayoutReversed;
use App\Jobs\AlertAdminJob;
use App\Jobs\SendPayoutJob;
use App\Models\CreditLedger;
use App\Models\PayoutAccount;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutFloatMovement;
use App\Models\PayoutProviderCall;
use App\Models\PayoutRequest;
use App\Models\PayoutWebhookEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Hardening\DestinationSnapshot;
use App\Services\Payouts\Hardening\PayoutInvariants;
use App\Services\Payouts\Hardening\PostRestoreCheck;
use App\Services\Payouts\LookupResult;
use App\Services\Payouts\PayoutEvent;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Payouts\SupportsStatusLookup;
use App\Support\PayoutSettings;
use App\Support\PayoutStatusText;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Addendum D (D-H1/D-H2): snapshot, job hardening, webhook dedupe, invariants, restore, returned payouts. */
class PayoutHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
    }

    /** A scriptable provider with optional lookup. */
    private function gateway(string $sendStatus = 'processing', ?callable $lookup = null): object
    {
        $gw = new class($sendStatus, $lookup) implements PayoutGatewayInterface, SupportsStatusLookup
        {
            public int $sends = 0;

            public function __construct(public string $status, public $lookup) {}

            public function name(): string { return 'paystack'; }

            public function available(): bool { return true; }

            public function createRecipient(PayoutAccount $account): string { return 'RCP'; }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                $this->sends++;

                return new PayoutTransferResult(status: $this->status, providerRef: 'TRF');
            }

            public function lookupTransfer(PayoutRequest $request): LookupResult
            {
                return $this->lookup ? ($this->lookup)($request) : LookupResult::unsupported();
            }

            public function verifyWebhook(Request $request): bool { return true; }

            public function parseWebhook(Request $request): ?PayoutEvent
            {
                return new PayoutEvent('paystack', (string) $request->input('data.reference'), (string) $request->input('status', 'paid'), 'TRF');
            }
        };
        $svc = new PayoutService([$gw]);
        $this->app->instance(PayoutService::class, $svc);
        $this->app->instance('payout.paystack', $gw);

        return $gw;
    }

    private function account(User $u, string $number = '0123456789'): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => $number, 'account_name' => 'Test User', 'provider' => 'paystack',
            'is_verified' => true, 'is_default' => true,
        ]);
    }

    /** A request whose hold exists in the credits ledger (the honest happy path). */
    private function heldRequest(?User $u = null, ?PayoutAccount $account = null): PayoutRequest
    {
        $u ??= User::factory()->create();
        $account ??= $this->account($u);
        $ref = 'wd:'.uniqid();
        CreditLedger::create(['user_id' => $u->id, 'type' => 'spend', 'source' => 'withdraw', 'withdrawable' => true, 'amount' => 5, 'balance_after' => 0, 'reference' => 'wd-hold:'.$ref]);
        $r = app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $account, $ref);
        $r->forceFill(['credit_amount' => 5])->save();

        return $r->fresh();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    // ── G-05 destination snapshot ──

    public function test_the_destination_is_frozen_when_the_request_is_made(): void
    {
        Queue::fake();
        $this->gateway();
        $r = $this->heldRequest();

        $snap = app(DestinationSnapshot::class)->read($r);

        $this->assertSame('paystack', $snap['provider']);
        $this->assertSame('******6789', $snap['identifier_masked']);
        $this->assertStringNotContainsString('0123456789', $r->getRawOriginal('destination_snapshot'), 'snapshot is encrypted at rest');
        $this->assertNotEmpty($r->destination_digest);
    }

    public function test_an_account_edited_after_the_request_is_not_sent_and_goes_to_manual_review(): void
    {
        Queue::fake();
        $gw = $this->gateway();
        $r = $this->heldRequest();
        $r->account->forceFill(['account_number' => '9999999999'])->save();   // takeover-style edit

        app(PayoutService::class)->send($r);

        $this->assertSame(0, $gw->sends, 'no provider call for a changed destination');
        $f = $r->fresh();
        $this->assertSame([PayoutRequest::PENDING, PayoutRequest::REVIEW_MANUAL, 'account_changed_after_request'], [$f->status, $f->review_state, $f->hold_reason]);
        $this->assertSame(0, PayoutProviderCall::count());
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_destination_changed');

        // A second attempt is a no-op for alerting.
        app(PayoutService::class)->send($f);
        $alerts = Queue::pushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_destination_changed');
        $this->assertCount(1, $alerts);
    }

    public function test_an_admin_can_accept_a_genuine_destination_change_then_it_sends(): void
    {
        Queue::fake();
        $gw = $this->gateway();
        $r = $this->heldRequest();
        $r->account->forceFill(['account_number' => '9999999999'])->save();
        app(PayoutService::class)->send($r);

        $this->expectException(PayoutException::class);
        try {
            app(PayoutService::class)->acceptDestinationChange($r->fresh(), $this->admin(), '  ');
        } finally {
            app(PayoutService::class)->acceptDestinationChange($r->fresh(), $this->admin(), 'Called the user, confirmed new account');
            app(PayoutService::class)->send($r->fresh());
            $this->assertSame(1, $gw->sends);
        }
    }

    public function test_a_deleted_account_cannot_be_acted_on_and_accounts_with_open_requests_cannot_be_removed(): void
    {
        Queue::fake();
        $this->gateway();
        $u = User::factory()->create();
        $account = $this->account($u);
        $r = $this->heldRequest($u, $account);

        try {
            app(PayoutAccountService::class)->remove($u, $account);
            $this->fail('removal of an in-use account must be refused');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('in progress', $e->getMessage());
        }

        // Even if it vanished some other way, the engine refuses to send without it.
        \DB::table('payout_requests')->where('id', $r->id)->update(['payout_account_id' => null]);
        $this->assertSame('account_deleted_after_request', app(DestinationSnapshot::class)->drift($r->fresh(), null));
        $this->expectException(PayoutException::class);
        app(PayoutService::class)->acceptDestinationChange($r->fresh(), $this->admin(), 'x');
    }

    public function test_an_unchanged_account_always_sends_even_when_it_was_passed_in_unsaved_defaults(): void
    {
        Queue::fake();
        $gw = $this->gateway();
        $u = User::factory()->create();
        $account = $this->account($u);                     // in-memory model: column defaults not hydrated
        $ref = 'wd:'.uniqid();
        CreditLedger::create(['user_id' => $u->id, 'type' => 'spend', 'source' => 'withdraw', 'withdrawable' => true, 'amount' => 5, 'balance_after' => 0, 'reference' => 'wd-hold:'.$ref]);
        $r = app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $account, $ref);

        app(PayoutService::class)->send($r);

        $this->assertSame(1, $gw->sends);
        $this->assertNull(app(DestinationSnapshot::class)->drift($r->fresh(), $r->fresh()->account));
    }

    public function test_legacy_requests_without_a_snapshot_still_send(): void
    {
        Queue::fake();
        $gw = $this->gateway();
        $r = $this->heldRequest();
        $r->forceFill(['destination_snapshot' => null, 'destination_digest' => null])->save();

        app(PayoutService::class)->send($r);

        $this->assertSame(1, $gw->sends);
    }

    // ── G-04 job hardening ──

    public function test_the_send_job_cannot_leave_a_stuck_unique_lock_or_outlive_the_http_timeout(): void
    {
        $job = new SendPayoutJob(1);
        $this->assertSame(900, $job->uniqueFor);
        $this->assertGreaterThan(PayoutSettings::httpTimeout(), $job->timeout);
        $this->assertSame(1, $job->tries);

        // The admin can never push the provider timeout above the worker timeout.
        Setting::setValue(PayoutSettings::HTTP_TIMEOUT, 500);
        $this->assertLessThan($job->timeout, PayoutSettings::httpTimeout());
    }

    // ── G-08 webhook dedupe ──

    public function test_a_duplicate_webhook_is_a_no_op_and_a_failed_apply_can_be_retried(): void
    {
        Queue::fake();
        $this->gateway();
        $r = $this->heldRequest();
        $r->forceFill(['status' => PayoutRequest::PROCESSING])->save();
        Event::fake([\App\Events\PayoutSettled::class]);

        $body = ['event' => 'transfer.success', 'status' => 'paid', 'data' => ['reference' => $r->reference]];
        $this->postJson('/webhooks/payouts/paystack', $body)->assertOk()->assertJsonMissing(['duplicate' => true]);
        $this->postJson('/webhooks/payouts/paystack', $body)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(1, PayoutWebhookEvent::count());
        $this->assertSame('applied', PayoutWebhookEvent::first()->outcome);
        $this->assertSame(PayoutRequest::PAID, $r->fresh()->status);
        Event::assertDispatchedTimes(\App\Events\PayoutSettled::class, 1);
        $this->assertStringContainsString($r->reference, PayoutWebhookEvent::first()->raw_payload);
        $this->assertStringNotContainsString($r->reference, \DB::table('payout_webhook_events')->value('raw_payload'), 'raw payload is encrypted at rest');
    }

    public function test_an_unknown_reference_webhook_is_recorded_not_applied(): void
    {
        $this->gateway();
        $this->postJson('/webhooks/payouts/paystack', ['event' => 'transfer.success', 'data' => ['reference' => 'nope']])->assertOk();
        $this->assertSame('unknown_reference', PayoutWebhookEvent::first()->outcome);
    }

    public function test_payloads_are_pruned_after_the_retention_window_but_the_dedupe_row_stays(): void
    {
        $this->gateway();
        PayoutWebhookEvent::create(['provider' => 'paystack', 'provider_event_id' => 'old', 'received_at' => now()->subDays(100), 'payload_hash' => 'x', 'raw_payload' => '{}']);
        PayoutWebhookEvent::create(['provider' => 'paystack', 'provider_event_id' => 'new', 'received_at' => now(), 'payload_hash' => 'y', 'raw_payload' => '{}']);

        $this->artisan('payouts:webhook-prune')->assertSuccessful();

        $this->assertNull(PayoutWebhookEvent::where('provider_event_id', 'old')->first()->raw_payload);
        $this->assertNotNull(PayoutWebhookEvent::where('provider_event_id', 'new')->first()->raw_payload);
    }

    // ── G-07 invariants ──

    public function test_invariants_pass_on_clean_books_and_fail_loudly_on_corrupted_ones(): void
    {
        Queue::fake();
        $this->gateway();
        $ok = $this->heldRequest();
        $ok->forceFill(['status' => PayoutRequest::PAID])->save();

        $clean = app(PayoutInvariants::class)->run('test', false);
        $this->assertSame(['ok', 0], [$clean->status, $clean->violation_count]);

        // 1. a PAID payout whose hold was released (money went out AND came back)
        CreditLedger::create(['user_id' => $ok->user_id, 'type' => 'earn', 'source' => 'withdraw_refund', 'withdrawable' => true, 'amount' => 5, 'balance_after' => 5, 'reference' => 'wd-refund:'.$ok->reference]);
        // 2. a FAILED payout whose hold was never released (user's money stuck)
        $stuck = $this->heldRequest();
        $stuck->forceFill(['status' => PayoutRequest::FAILED])->save();
        // 3. an OPEN payout with no hold at all (unfunded)
        $u = User::factory()->create();
        $unfunded = app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $this->account($u), 'wd:unfunded');
        $unfunded->forceFill(['credit_amount' => 5])->save();
        // 4. a float chain that does not add up
        PayoutFloatBalance::create(['provider' => 'paystack', 'currency' => 'NGN', 'balance' => 999]);
        PayoutFloatMovement::create(['provider' => 'paystack', 'currency' => 'NGN', 'type' => 'topup', 'amount' => 100, 'balance_after' => 100]);

        Queue::fake();
        $run = app(PayoutInvariants::class)->run('test');

        $this->assertSame('violations', $run->status);
        $byId = collect($run->results)->keyBy('id');
        $this->assertFalse($byId['paid_hold_consumed']['ok']);
        $this->assertSame($ok->id, $byId['paid_hold_consumed']['offenders'][0]['request_id']);
        $this->assertFalse($byId['failed_hold_released']['ok']);
        $this->assertSame($stuck->id, $byId['failed_hold_released']['offenders'][0]['request_id']);
        $this->assertFalse($byId['open_hold_live']['ok']);
        $this->assertFalse($byId['float_chain']['ok']);
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'invariant_violation');
        $this->artisan('payouts:invariants-check --no-alert')->assertFailed();
    }

    public function test_duplicate_provider_references_are_caught(): void
    {
        Queue::fake();
        $a = $this->heldRequest();
        $b = $this->heldRequest();
        $a->forceFill(['provider_ref' => 'SAME'])->save();
        $b->forceFill(['provider_ref' => 'SAME'])->save();

        $run = app(PayoutInvariants::class)->run('test', false);

        $this->assertFalse(collect($run->results)->firstWhere('id', 'references_unique')['ok']);
    }

    // ── G-03 restore protocol ──

    public function test_post_restore_check_pauses_payouts_finds_the_stale_request_and_applies_only_through_confirm(): void
    {
        Queue::fake();
        $this->gateway('processing', fn () => LookupResult::found('paid', 'TRF'));
        $r = $this->heldRequest();
        $r->forceFill(['status' => PayoutRequest::PROCESSING])->save();   // restore rolled us back to "processing"
        Setting::setValue(PayoutSettings::AUTO_APPROVAL, true);

        $this->artisan('payouts:post-restore-check')->assertFailed();     // mismatch found, nothing applied
        $this->assertFalse(PayoutSettings::enabled());
        $this->assertFalse(PayoutSettings::autoApprovalEnabled());
        $this->assertSame(PayoutRequest::PROCESSING, $r->fresh()->status);

        $res = app(PostRestoreCheck::class)->run(null, true);

        $this->assertSame(1, $res['applied']);
        $this->assertSame(PayoutRequest::PAID, $r->fresh()->status);
    }

    public function test_post_restore_check_flags_a_request_the_provider_never_heard_of_that_we_call_paid(): void
    {
        Queue::fake();
        $this->gateway('processing', fn () => LookupResult::notFound());
        $r = $this->heldRequest();
        $r->forceFill(['status' => PayoutRequest::PAID])->save();

        $res = app(PostRestoreCheck::class)->run(now()->subDay());

        $this->assertSame('review', $res['mismatches'][0]['action']);
        $this->assertSame(PayoutRequest::PAID, $r->fresh()->status, 'never auto-changed');
    }

    // ── G-09 returned payouts ──

    public function test_a_returned_payout_is_re_credited_once_flags_the_account_and_nets_to_zero(): void
    {
        Queue::fake();
        $this->gateway();
        $r = $this->heldRequest();
        $r->forceFill(['status' => PayoutRequest::PAID])->save();
        Event::fake([PayoutReversed::class]);

        $svc = app(PayoutService::class);
        $svc->markReturned($r, 'Bank returned: account closed', $this->admin());
        $svc->markReturned($r->fresh(), 'duplicate notification', $this->admin());

        $f = $r->fresh();
        $this->assertSame(PayoutRequest::RETURNED, $f->status);
        $this->assertTrue($f->isFinal());
        Event::assertDispatchedTimes(PayoutReversed::class, 1);
        $this->assertSame('needs_attention', $f->account->provider_status);
        $this->assertSame('bounced', PayoutStatusText::key($f));
        foreach (['en', 'fr', 'sw', 'ar'] as $l) {
            app()->setLocale($l);
            $this->assertNotSame('payouts.status.bounced', PayoutStatusText::text($f), "$l missing");
        }
    }

    public function test_returned_only_applies_to_paid_and_needs_evidence_and_a_second_return_locks_the_account(): void
    {
        Queue::fake();
        $this->gateway();
        $u = User::factory()->create();
        $account = $this->account($u);
        $pending = $this->heldRequest($u, $account);

        app(PayoutService::class)->markReturned($pending, 'x', $this->admin());
        $this->assertSame(PayoutRequest::PENDING, $pending->fresh()->status, 'only a PAID payout can be returned');

        try {
            app(PayoutService::class)->markReturned($pending, '   ', $this->admin());
            $this->fail('evidence is required');
        } catch (PayoutException) {
        }

        foreach ([1, 2] as $i) {
            $r = $this->heldRequest($u, $account->fresh());
            $r->forceFill(['status' => PayoutRequest::PAID])->save();
            app(PayoutService::class)->markReturned($r, "return {$i}", $this->admin());
        }
        $account = $account->fresh();
        $this->assertSame('locked', $account->provider_status);
        $this->assertFalse($account->is_verified);
    }

    public function test_a_provider_returned_event_drives_the_same_path(): void
    {
        Queue::fake();
        $this->gateway();
        $r = $this->heldRequest();
        $r->forceFill(['status' => PayoutRequest::PAID])->save();

        app(PayoutService::class)->applyWebhook(new PayoutEvent('paystack', $r->reference, 'returned', 'TRF'));

        $this->assertSame(PayoutRequest::RETURNED, $r->fresh()->status);
    }
}
