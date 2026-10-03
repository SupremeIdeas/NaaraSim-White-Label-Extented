<?php

namespace App\Services\Payouts;

use App\Events\PayoutReversed;
use App\Events\PayoutSettled;
use App\Jobs\AlertAdminJob;
use App\Jobs\EvaluatePayoutRequestJob;
use App\Jobs\SendPayoutJob;
use App\Models\PaymentCharge;
use App\Models\PayoutAccount;
use App\Models\PayoutDecision;
use App\Models\PayoutProviderCall;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\Hardening\DestinationSnapshot;
use App\Support\Auditor;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The payout engine (ROADMAP §Layer 0.2) — the single owner of the money-out
 * lifecycle, holding the same discipline as WalletService:
 *
 *   - Idempotent: a payout_request is keyed by a unique reference; the same
 *     reference never creates or sends a second transfer.
 *   - Row-locked send: the actual transfer runs under a lockForUpdate so two
 *     workers can't double-send.
 *   - Webhook-confirmed truth: a request is only `paid` once the PSP webhook
 *     confirms it — never on the synchronous send response alone.
 *   - Never blind-retry: a failed transfer is marked failed, a PayoutReversed
 *     event returns the held source funds, and the admin is alerted. A retry is
 *     an explicit new request, not an automatic resend.
 *
 * The engine is SOURCE-AGNOSTIC: the caller (NaaraCredit cash-out, merchant
 * settlement) holds the funds before createRequest() and listens for
 * PayoutReversed/PayoutSettled to release or clear the hold.
 */
class PayoutService
{
    /** @param list<PayoutGatewayInterface> $gateways */
    public function __construct(private array $gateways, private ?PayoutAdmission $admission = null) {}

    public function gatewayFor(?string $provider): ?PayoutGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->name() === $provider && $gateway->available()) {
                // A live key outside live mode (or a test key inside it) is treated as "not available" (D-3.16).
                return \App\Services\Payouts\Hardening\PayoutEnvGuard::allows($provider) ? $gateway : null;
            }
        }

        return null;
    }

    /** Is this provider registered at all (whether or not it is currently configured/available)? */
    public function knowsProvider(?string $provider): bool
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->name() === $provider) {
                return true;
            }
        }

        return false;
    }

    private const RANK_CACHE = 'payouts.gateway_ranking.v1';

    /** Names of currently-available payout-capable gateways. */
    public function payoutCapableNames(): array
    {
        return array_values(array_map(
            fn (PayoutGatewayInterface $g) => $g->name(),
            array_filter($this->gateways, fn (PayoutGatewayInterface $g) => $g->available()),
        ));
    }

    /**
     * Payout-capable gateways ranked by the platform's REAL recent inbound volume
     * (BUILD-4 §8) — don't push disbursements through a rail nobody pays in
     * through, since it won't hold reliable float. Inbound volume is read per
     * gateway from payment_charges (the gateway-attributed inbound record).
     * Cached (daily via payouts:rank) so it's never recomputed per page load.
     *
     * @return array<string, float> gateway => inbound volume, highest first
     */
    public function rankedGateways(int $days = 90): array
    {
        $volume = Cache::remember(self::RANK_CACHE, now()->addDay(),
            fn () => PaymentCharge::query()
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('gateway, SUM(amount) as vol')
                ->groupBy('gateway')
                ->pluck('vol', 'gateway')
                ->map(fn ($v) => (float) $v)
                ->all());

        $ranked = [];
        foreach ($this->payoutCapableNames() as $name) {
            $ranked[$name] = (float) ($volume[$name] ?? 0);
        }
        arsort($ranked);

        return $ranked;
    }

    /**
     * The single "Recommended — fast payout" gateway: the highest-inbound-volume
     * payout-capable gateway, or null when none has any real inbound volume yet
     * (so we never badge a rail we can't back). Other rails still show — the UI
     * only highlights, never hides (§8.3).
     */
    public function recommendedGateway(int $days = 90): ?string
    {
        foreach ($this->rankedGateways($days) as $name => $vol) {
            if ($vol > 0) {
                return $name;
            }
        }

        return null;
    }

    /** Drop the cached ranking (called by the daily payouts:rank command). */
    public function flushRanking(): void
    {
        Cache::forget(self::RANK_CACHE);
    }

    /**
     * Create a withdrawal request. The caller has ALREADY held the source funds.
     * Idempotent by reference; validates the destination belongs to the payee and
     * was PSP-verified, then runs the race-proof admission check (open-request cap +
     * free-payout/KYC limit) under a lock on the payee's row, and finally queues the
     * Payout Guardian's evaluation AFTER the surrounding transaction commits — never
     * a provider call here (money rule 8).
     *
     * `$quote` carries the locked FX quote (usd_amount, fx_rate, platform_fee_usd,
     * corridor_id, quote_expires_at) when the caller has one.
     *
     * @param  array<string, mixed>  $quote
     * @throws PayoutException
     */
    public function createRequest(
        User $payee,
        float $amount,
        string $currency,
        string $sourceBucket,
        PayoutAccount $account,
        string $reference,
        array $quote = [],
    ): PayoutRequest {
        if ($account->user_id !== $payee->id) {
            throw new PayoutException('Payout account does not belong to this user.');
        }
        if (! $account->is_verified) {
            throw new PayoutException('Payout account is not verified.');
        }
        if ($amount <= 0) {
            throw new PayoutException('Payout amount must be positive.');
        }

        $request = DB::transaction(function () use ($payee, $amount, $currency, $sourceBucket, $account, $reference, $quote) {
            $existing = PayoutRequest::query()->where('reference', $reference)->first();
            if ($existing !== null) {
                return $existing; // idempotent — never a second payout for the same reference
            }

            // Serialise concurrent requests from the same user: the second waits here,
            // then sees the first as an in-flight (committed) request.
            User::query()->whereKey($payee->id)->lockForUpdate()->first();
            ($this->admission ?? app(PayoutAdmission::class))->check($payee, $sourceBucket, $account->provider);

            $request = PayoutRequest::create([
                'user_id' => $payee->id,
                'payout_account_id' => $account->id,
                'amount' => round($amount, 4),
                'currency' => strtoupper($currency),
                'source_bucket' => $sourceBucket,
                'status' => PayoutRequest::PENDING,
                'review_state' => PayoutRequest::REVIEW_AUTO_PENDING,
                'provider' => $account->provider,
                'reference' => $reference,
            ] + array_intersect_key($quote, array_flip([
                'usd_amount', 'fx_rate', 'platform_fee_usd', 'corridor_id', 'quote_expires_at', 'fx_locked_at',
            ])));

            // Freeze WHERE this is going, in the same transaction as the request (Addendum D-3.1).
            app(DestinationSnapshot::class)->stamp($request, $account);
            \App\Services\Payouts\Hardening\PayoutReference::ensure($request);

            Auditor::log('payout.requested', 'PayoutRequest', $request->id, [
                'amount' => $request->amount, 'currency' => $request->currency, 'source' => $sourceBucket,
            ]);

            return $request;
        });

        // The Guardian looks at every NEW request (it only approves when auto mode
        // is on and its gates pass; otherwise it routes to the admin queue).
        if ($request->wasRecentlyCreated) {
            \App\Events\PayoutRequested::dispatch($request);
            EvaluatePayoutRequestJob::dispatch($request->id)->afterCommit();
        }

        return $request;
    }

    /**
     * Admin approves a pending request and queues the transfer. The external PSP
     * call always runs in a job (money rule 8). Uses the same compare-and-set as the
     * Guardian, so an admin and the system approving the same request at the same
     * moment produce exactly one SendPayoutJob.
     */
    public function approve(PayoutRequest $request, User $approver, ?string $note = null): PayoutRequest
    {
        abort_unless($this->mayReview($approver), 403);
        $this->guardDualControl($request, $approver);

        if (! $this->claimApproval($request, PayoutRequest::APPROVAL_ADMIN, PayoutRequest::REVIEW_APPROVED_MANUAL, $approver->id)) {
            return $request->refresh(); // someone else already actioned it
        }
        $this->recordAdminDecision($request, $approver, 'approve', $note);

        Auditor::log('payout.approved', 'PayoutRequest', $request->id, ['approved_by' => $approver->id]);
        $this->dispatchSend($request);

        return $request->refresh();
    }

    /**
     * The Payout Guardian approves a request that passed every gate. `$decision` is
     * the append-only record that justified it. Returns false (and dispatches
     * NOTHING) if an admin or another worker already actioned the request.
     */
    public function approveBySystem(PayoutRequest $request, PayoutDecision $decision): bool
    {
        if (! $this->claimApproval($request, PayoutRequest::APPROVAL_SYSTEM, PayoutRequest::REVIEW_APPROVED_AUTO, null)) {
            return false;
        }

        Auditor::log('payout.approved_system', 'PayoutRequest', $request->id, ['decision_id' => $decision->id, 'score' => $decision->score]);
        $this->dispatchSend($request);

        return true;
    }

    /**
     * Queue the send. A NEW destination (no earlier delivered payout to it) waits a short, configurable
     * delay first — the user's cancel window and the account-takeover brake (Addendum D-3.5). 0 = no delay.
     */
    private function dispatchSend(PayoutRequest $request): void
    {
        $global = \App\Services\Payouts\Rail\RailEnrollmentService::isGlobal($request->provider);
        $delay = PayoutSettings::sendDelayMinutes($global);
        $isNew = $delay > 0 && ! PayoutRequest::query()
            ->where('payout_account_id', $request->payout_account_id)->where('status', PayoutRequest::PAID)->exists();

        $job = SendPayoutJob::dispatch($request->id)->afterCommit();
        if ($isNew) {
            $job->delay(now()->addMinutes($delay));
        }
    }

    /**
     * The payee cancels their own request while nothing has reached the provider (pending / approved /
     * awaiting funds and no provider call exists). Idempotent; the hold comes back through the same
     * PayoutReversed path every other reversal uses.
     */
    public function cancelByUser(PayoutRequest $request, User $user): bool
    {
        abort_unless($request->user_id === $user->id, 403);

        return $this->cancel($request, 'Cancelled by you', $user->id);
    }

    public function cancel(PayoutRequest $request, string $reason, ?int $byUserId = null): bool
    {
        $won = PayoutRequest::query()
            ->whereKey($request->id)
            ->whereIn('status', [PayoutRequest::PENDING, PayoutRequest::AWAITING_FUNDS, PayoutRequest::APPROVED])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payout_provider_calls')->whereColumn('payout_provider_calls.payout_request_id', 'payout_requests.id'))
            ->update([
                'status' => PayoutRequest::REVERSED, 'failure_reason' => $reason, 'review_state' => PayoutRequest::REVIEW_REJECTED_AUTO,
                'next_check_at' => null, 'evaluating_at' => null, 'updated_at' => now(),
            ]) === 1;
        if (! $won) {
            return false;
        }

        Auditor::log('payout.cancelled', 'PayoutRequest', $request->id, ['by' => $byUserId, 'reason' => $reason]);
        PayoutReversed::dispatch($request->refresh());

        return true;
    }

    /**
     * One atomic compare-and-set: pending → approved. Zero rows affected means
     * another actor won; the caller must not dispatch a second job.
     */
    private function claimApproval(PayoutRequest $request, string $source, string $reviewState, ?int $approverId): bool
    {
        $affected = PayoutRequest::query()
            ->whereKey($request->id)
            ->where('status', PayoutRequest::PENDING)
            ->update([
                'status' => PayoutRequest::APPROVED,
                'approval_source' => $source,
                'review_state' => $reviewState,
                'approved_by' => $approverId,
                'hold_reason' => null,
                'next_check_at' => null,
                'updated_at' => now(),
            ]);

        return $affected === 1;
    }

    /**
     * Perform the transfer — crash-safe, and NEVER holding a DB lock across the
     * provider call.
     *
     *  1. Short row-locked transaction: claim the request (→ `processing`, so it can
     *     never be sent twice) and write an INTENT row in payout_provider_calls.
     *  2. Outside any transaction: mark the call `submitted`, call the provider.
     *  3. Classify the outcome:
     *       definitive rejection        → reverse the hold (the money never left)
     *       accepted / settled          → keep `processing` (webhook confirms) / `paid`
     *       ANY timeout, 5xx, transport error or dead worker after submit
     *                                   → `unknown`: stay `processing`, NO reversal.
     *     Only the reconciler (provider status lookup) or a human may conclude an
     *     unknown outcome — refunding a payout the provider actually made would be a
     *     double pay.
     */
    public function send(PayoutRequest $request): PayoutRequest
    {
        [$fresh, $gateway, $account, $call] = DB::transaction(function () use ($request) {
            /** @var PayoutRequest $fresh */
            $fresh = PayoutRequest::query()->whereKey($request->id)->lockForUpdate()->first();
            if (! in_array($fresh->status, [PayoutRequest::PENDING, PayoutRequest::APPROVED], true)) {
                return [$fresh, null, null, null]; // already sent/finalised
            }

            $account = $fresh->account;

            // The destination was frozen when the request was made. If the account was edited or
            // deleted since, do NOT send — a human decides (that is what a takeover looks like).
            if (($drift = app(DestinationSnapshot::class)->drift($fresh, $account)) !== null) {
                $this->holdForDestinationChange($fresh, $drift);

                return [$fresh, null, null, null];
            }

            $gateway = $this->gatewayFor($fresh->provider);
            if ($account === null || $gateway === null) {
                $this->finalizeFailure($fresh, 'No available payout provider for this account.');

                return [$fresh, null, null, null];
            }

            // Phase 4: never send into an empty provider account. A tracked rail whose float
            // cannot cover this payout (or has older ones queued ahead of it) parks the
            // request in `awaiting_funds` — the user's funds stay held; it resumes by itself.
            $float = app(FloatService::class);
            if (! $float->covers($fresh)) {
                $float->markAwaiting($fresh);

                return [$fresh, null, null, null];
            }

            $call = PayoutProviderCall::create([
                'payout_request_id' => $fresh->id,
                'provider' => $gateway->name(),
                'attempt' => (int) PayoutProviderCall::where('payout_request_id', $fresh->id)->max('attempt') + 1,
                'idempotency_key' => $fresh->reference,
                'state' => PayoutProviderCall::INTENT,
                'started_at' => now(),
            ]);
            $fresh->forceFill(['status' => PayoutRequest::PROCESSING])->save();
            $float->debitForPayout($fresh); // draws float in the same transaction as the claim — never twice

            return [$fresh, $gateway, $account, $call];
        });

        if ($call === null) {
            return $fresh;
        }

        // Recipient creation moves no money, so a failure here is a definitive one.
        try {
            if (empty($account->provider_recipient_ref)) {
                $account->forceFill(['provider_recipient_ref' => $gateway->createRecipient($account)])->save();
            }
        } catch (Throwable $e) {
            return $this->definitiveFailure($fresh, $call, 'Recipient error: '.$e->getMessage(), $this->httpStatus($e));
        }

        $call->forceFill(['state' => PayoutProviderCall::SUBMITTED])->save();

        try {
            $result = $gateway->sendTransfer($fresh, $account);
        } catch (Throwable $e) {
            if ($this->isDefinitiveRejection($e)) {
                return $this->definitiveFailure($fresh, $call, 'Transfer rejected: '.$e->getMessage(), $this->httpStatus($e));
            }

            return $this->markUnknown($fresh, $call, $e->getMessage(), $this->httpStatus($e));
        }

        if ($result->status === 'failed') {
            return $this->definitiveFailure($fresh, $call, $result->failureReason ?? 'Transfer rejected by provider.');
        }

        $call->forceFill([
            'state' => PayoutProviderCall::CONFIRMED, 'provider_ref' => $result->providerRef,
            'finished_at' => now(), 'next_lookup_at' => null,
        ])->save();

        // A webhook may already have finalised the request while we were on the wire.
        $current = $fresh->refresh();
        if ($current->status !== PayoutRequest::PROCESSING) {
            return $current;
        }

        $current->forceFill(['provider_ref' => $result->providerRef ?? $current->provider_ref])->save();

        return $result->status === 'paid' ? $this->confirm($current, $result->providerRef) : $current;
    }

    /** Who may decide payout requests: admins, or staff granted the `payouts.review` scope (Addendum D-3.19). */
    public function mayReview(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) || $user->can('payouts.review');
    }

    /**
     * Dual control (off by default): whoever just recorded a float top-up cannot also approve a payout at or
     * above the configured USD amount — the person who adds money must not be the one who releases it.
     */
    private function guardDualControl(PayoutRequest $request, User $approver): void
    {
        $limit = PayoutSettings::dualControlUsd();
        if ($limit <= 0 || (float) ($request->usd_amount ?? 0) < $limit) {
            return;
        }
        $recent = \App\Models\PayoutFloatMovement::query()->where('created_by', $approver->id)->whereIn('type', ['topup', 'adjustment'])
            ->where('created_at', '>=', now()->subDay())->exists();
        if ($recent) {
            throw new PayoutException('Dual control: you recorded float funding in the last 24 hours, so another reviewer must approve a payout this large.');
        }
    }

    /** Park a request whose destination changed after it was made; alert once; no provider call. */
    private function holdForDestinationChange(PayoutRequest $request, string $reason): void
    {
        $first = $request->hold_reason !== $reason;
        $request->forceFill([
            'status' => PayoutRequest::PENDING,
            'review_state' => PayoutRequest::REVIEW_MANUAL,
            'hold_reason' => $reason,
            'next_check_at' => null,
        ])->save();

        if ($first) {
            Auditor::log('payout.destination_changed', 'PayoutRequest', $request->id, ['reason' => $reason]);
            AlertAdminJob::dispatch(
                code: 'payout_destination_changed',
                message: "Payout #{$request->id} was NOT sent: the payout account was changed or removed after the request was made ({$reason}). Check with the user before releasing or rejecting it.",
                context: ['payout_id' => $request->id, 'user_id' => $request->user_id, 'reason' => $reason],
            );
        }
    }

    /**
     * An admin confirms the changed destination is genuine: the snapshot is re-taken from the
     * live account and the request returns to the queue. Needs a note; audited; impossible if the
     * account no longer exists (reject instead).
     */
    public function acceptDestinationChange(PayoutRequest $request, User $admin, string $note): PayoutRequest
    {
        abort_unless($this->mayReview($admin), 403);
        if (trim($note) === '') {
            throw new PayoutException('A note is required.');
        }
        $account = $request->account;
        if ($account === null) {
            throw new PayoutException('The payout account no longer exists — reject this request instead.');
        }
        if (in_array($request->status, [PayoutRequest::PROCESSING, PayoutRequest::PAID], true) || $request->isFinal()) {
            throw new PayoutException('This request has already been sent or finalised.');
        }

        app(DestinationSnapshot::class)->stamp($request, $account);
        $request->forceFill(['hold_reason' => null])->save();
        Auditor::log('payout.destination_accepted', 'PayoutRequest', $request->id, ['by' => $admin->id, 'note' => $note]);

        return $request->refresh();
    }

    /** The provider definitively refused: nothing moved, so the hold is safely reversed. */
    private function definitiveFailure(PayoutRequest $request, PayoutProviderCall $call, string $reason, ?int $http = null): PayoutRequest
    {
        $call->forceFill([
            'state' => PayoutProviderCall::DEFINITIVELY_FAILED, 'error' => $reason,
            'http_status' => $http, 'finished_at' => now(), 'next_lookup_at' => null,
        ])->save();

        return $this->finalizeFailure($request->refresh(), $reason);
    }

    /** Timeout / 5xx / transport error: we do NOT know whether the provider acted. */
    private function markUnknown(PayoutRequest $request, PayoutProviderCall $call, string $error, ?int $http = null): PayoutRequest
    {
        $call->forceFill([
            'state' => PayoutProviderCall::UNKNOWN, 'error' => mb_substr($error, 0, 1000), 'http_status' => $http,
            'finished_at' => now(), 'next_lookup_at' => now()->addMinutes(2),
        ])->save();
        Auditor::log('payout.unknown_outcome', 'PayoutRequest', $request->id, ['provider' => $call->provider, 'http' => $http]);

        return $request->refresh();
    }

    /** Only these mean "the provider looked at it and said no" — everything else is unknown. */
    private function isDefinitiveRejection(Throwable $e): bool
    {
        if ($e instanceof PayoutException) {
            return true; // our own pre-flight refusal, raised before any network I/O
        }
        if ($e instanceof ConnectionException) {
            return false; // could have timed out after the provider accepted it
        }
        if ($e instanceof RequestException) {
            return in_array($e->response->status(), [400, 401, 402, 403, 404, 405, 422, 429], true);
        }

        return false;
    }

    private function httpStatus(Throwable $e): ?int
    {
        return $e instanceof RequestException ? $e->response->status() : null;
    }

    /**
     * Admin declines a pending request before it's sent. Reverses the hold (the
     * source layer returns the funds) — nothing was ever sent to the PSP. Uses the
     * same compare-and-set as approval: if the request is no longer `pending`
     * (an approval won the race) nothing happens.
     */
    public function reject(PayoutRequest $request, User $approver, string $reason = 'Declined by admin', ?string $note = null): PayoutRequest
    {
        abort_unless($this->mayReview($approver), 403);

        if (! $this->claimRejection($request, $reason, $approver->id, null)) {
            return $request->refresh(); // only a not-yet-sent request can be declined here
        }
        $this->recordAdminDecision($request, $approver, 'reject', $note);
        Auditor::log('payout.rejected', 'PayoutRequest', $request->id, ['by' => $approver->id, 'reason' => $reason]);

        PayoutReversed::dispatch($request->refresh());

        return $request;
    }

    /**
     * The Guardian declines a request (e.g. over a hard per-user cap). `$reason` is a
     * SAFE, user-facing sentence — never a rule name or a score.
     */
    public function rejectBySystem(PayoutRequest $request, string $reason, PayoutDecision $decision): bool
    {
        if (! $this->claimRejection($request, $reason, null, PayoutRequest::REVIEW_REJECTED_AUTO)) {
            return false;
        }
        Auditor::log('payout.rejected_system', 'PayoutRequest', $request->id, ['decision_id' => $decision->id]);

        PayoutReversed::dispatch($request->refresh());

        return true;
    }

    /** A human's decision goes in the same append-only log as the Guardian's, with their note. */
    private function recordAdminDecision(PayoutRequest $request, User $admin, string $decision, ?string $note): void
    {
        PayoutDecision::create([
            'payout_request_id' => $request->id,
            'attempt' => (int) PayoutDecision::where('payout_request_id', $request->id)->max('attempt') + 1,
            'decision' => $decision,
            'reason' => $note !== null ? mb_substr($note, 0, 64) : 'admin',
            'score' => (int) $request->risk_score,
            'rules' => [['id' => 'admin_decision', 'result' => 'pass', 'weight' => 0, 'evidence' => ['note' => $note, 'admin_id' => $admin->id]]],
            'engine_version' => 'admin',
            'shadow' => false,
            'decided_by' => 'admin:'.$admin->id,
            'decided_at' => now(),
        ]);
    }

    /** pending → reversed in one atomic statement; false if anyone else already actioned it. */
    private function claimRejection(PayoutRequest $request, string $reason, ?int $approverId, ?string $reviewState): bool
    {
        return PayoutRequest::query()->whereKey($request->id)->whereIn('status', [PayoutRequest::PENDING, PayoutRequest::AWAITING_FUNDS])->update([
            'status' => PayoutRequest::REVERSED,
            'failure_reason' => $reason,
            'approved_by' => $approverId,
            'review_state' => $reviewState ?? $request->review_state,
            'next_check_at' => null,
            'evaluating_at' => null,
            'updated_at' => now(),
        ]) === 1;
    }

    /** Apply a verified webhook event to its request (idempotent). */
    public function applyWebhook(PayoutEvent $event): ?PayoutRequest
    {
        // The provider echoes back whichever reference we gave it: the derived one, or (older
        // requests) our internal one.
        $request = PayoutRequest::query()->where('provider_reference', $event->reference)->first()
            ?? PayoutRequest::query()->where('reference', $event->reference)->first();
        if ($request === null) {
            return null;
        }

        return match ($event->status) {
            'paid' => $this->confirm($request, $event->providerRef),
            'failed', 'reversed' => $this->fail($request, 'Provider reported: '.$event->status),
            'returned' => $this->markReturned($request, 'Provider reported the transfer was returned.', null, $event->providerRef),
            default => $request,
        };
    }

    /** Mark a request paid (webhook-confirmed). Idempotent. */
    public function confirm(PayoutRequest $request, ?string $providerRef = null): PayoutRequest
    {
        if ($request->status === PayoutRequest::PAID) {
            return $request;
        }
        // A 'paid' confirmation for an already-reversed/failed payout is a
        // conflict — the held funds were already returned. Never silently flip to
        // PAID (that double-pays); freeze the state and alert for reconciliation.
        if (in_array($request->status, [PayoutRequest::FAILED, PayoutRequest::REVERSED], true)) {
            AlertAdminJob::dispatch(
                code: 'payout_confirm_conflict',
                message: "Payout #{$request->id} reported PAID by the PSP but is already {$request->status} (held funds returned). Manual reconciliation needed.",
                context: ['payout_id' => $request->id, 'amount' => $request->amount, 'currency' => $request->currency],
            );

            return $request;
        }

        $request->forceFill([
            'status' => PayoutRequest::PAID,
            'provider_ref' => $providerRef ?? $request->provider_ref,
            'settled_at' => now(),
        ])->save();
        $this->closeOpenCalls($request, PayoutProviderCall::CONFIRMED);
        Auditor::log('payout.paid', 'PayoutRequest', $request->id);
        PayoutSettled::dispatch($request);

        return $request;
    }

    /**
     * Plan-B payout: an admin has paid the person OUTSIDE the platform and records the proof. Only a request
     * on the manual_external rail that the engine has already moved to `processing` can be settled this way;
     * the evidence note and a proof reference (bank transfer id, receipt no.) are both required and audited.
     * The same idempotent confirm() every provider confirmation uses does the rest.
     */
    public function recordManualPayment(PayoutRequest $request, User $admin, string $proofReference, string $note): PayoutRequest
    {
        abort_unless($this->mayReview($admin), 403);
        if ($request->provider !== ManualExternalPayoutGateway::NAME) {
            throw new PayoutException('Only manual payouts can be settled by recording proof.');
        }
        if (trim($proofReference) === '' || trim($note) === '') {
            throw new PayoutException('A proof reference and a note are required.');
        }
        if ($request->status !== PayoutRequest::PROCESSING) {
            throw new PayoutException('This payout is not waiting to be paid.');
        }

        Auditor::log('payout.manual_paid', 'PayoutRequest', $request->id, ['by' => $admin->id, 'proof' => $proofReference, 'note' => $note]);

        return $this->confirm($request, 'manual:'.mb_substr(trim($proofReference), 0, 120));
    }

    /**
     * The provider/bank sent the money back AFTER we reported it paid (wrong details, closed
     * account, compliance reject). paid → returned in ONE compare-and-set, then the original
     * earnings bucket is re-credited exactly once (the existing release listeners are idempotent
     * on the reference), the destination is flagged, and the user and admin are told.
     * Never auto-resubmits. Reached only from `paid`, with evidence.
     */
    public function markReturned(PayoutRequest $request, string $evidence, ?User $admin = null, ?string $providerRef = null): PayoutRequest
    {
        if ($admin !== null) {
            abort_unless($this->mayReview($admin), 403);
            if (trim($evidence) === '') {
                throw new PayoutException('Evidence is required to mark a payout returned.');
            }
        }

        $won = PayoutRequest::query()->whereKey($request->id)->where('status', PayoutRequest::PAID)->update([
            'status' => PayoutRequest::RETURNED, 'failure_reason' => mb_substr($evidence, 0, 500),
            'provider_ref' => $providerRef ?? $request->provider_ref, 'updated_at' => now(),
        ]) === 1;
        if (! $won) {
            return $request->refresh(); // not paid (or already returned): nothing to do
        }
        $request->refresh();

        Auditor::log('payout.returned', 'PayoutRequest', $request->id, ['by' => $admin?->id, 'evidence' => $evidence]);
        $this->flagReturnedDestination($request);

        // Same idempotent path a failure uses to give a hold back — keyed on the reference, so a
        // repeated notification can never credit the bucket twice.
        PayoutReversed::dispatch($request);
        AlertAdminJob::dispatch(
            code: 'payout_returned',
            message: "Payout #{$request->id} was returned by the provider/bank after being reported paid. Funds were re-credited to the user's bucket; their payout account is flagged. Evidence: {$evidence}",
            context: ['payout_id' => $request->id, 'amount' => $request->amount, 'currency' => $request->currency],
        );

        return $request;
    }

    /** One return flags the destination; two on the same destination lock it pending review. */
    private function flagReturnedDestination(PayoutRequest $request): void
    {
        $account = $request->account;
        if ($account === null) {
            return;
        }
        $returns = PayoutRequest::query()->where('status', PayoutRequest::RETURNED)->where('payout_account_id', $account->id)->count();
        $account->forceFill($returns >= 2
            ? ['provider_status' => 'locked', 'is_verified' => false]
            : ['provider_status' => 'needs_attention'])->save();
    }

    /** Mark a request failed and reverse the hold. Idempotent. */
    public function fail(PayoutRequest $request, string $reason): PayoutRequest
    {
        if (in_array($request->status, [PayoutRequest::FAILED, PayoutRequest::REVERSED], true)) {
            return $request;
        }
        // A 'failed' report for an already-PAID transfer is a conflict — reversing
        // it would return funds after the cash left. Freeze the PAID state and
        // alert for reconciliation instead of blindly reversing.
        if ($request->status === PayoutRequest::PAID) {
            AlertAdminJob::dispatch(
                code: 'payout_fail_conflict',
                message: "Payout #{$request->id} reported {$reason} by the PSP but is already PAID. Manual reconciliation needed.",
                context: ['payout_id' => $request->id, 'amount' => $request->amount, 'currency' => $request->currency],
            );

            return $request;
        }

        return $this->finalizeFailure($request, $reason);
    }

    /** Whatever the provider call was doing, the request is now settled one way or the other. */
    private function closeOpenCalls(PayoutRequest $request, string $state): void
    {
        PayoutProviderCall::query()
            ->where('payout_request_id', $request->id)
            ->whereIn('state', [PayoutProviderCall::INTENT, PayoutProviderCall::SUBMITTED, PayoutProviderCall::UNKNOWN])
            ->update(['state' => $state, 'finished_at' => now(), 'next_lookup_at' => null]);
    }

    private function finalizeFailure(PayoutRequest $request, string $reason): PayoutRequest
    {
        $request->forceFill([
            'status' => PayoutRequest::FAILED,
            'failure_reason' => $reason,
        ])->save();
        $this->closeOpenCalls($request, PayoutProviderCall::DEFINITIVELY_FAILED);
        app(FloatService::class)->reverseDebit($request); // the provider did not pay: float comes back
        Auditor::log('payout.failed', 'PayoutRequest', $request->id, ['reason' => $reason]);

        // Return the held source funds and alert — never blind-retry.
        PayoutReversed::dispatch($request);
        AlertAdminJob::dispatch(
            code: 'payout_failed',
            message: "Payout #{$request->id} to user {$request->user_id} failed: {$reason}",
            context: ['payout_id' => $request->id, 'amount' => $request->amount, 'currency' => $request->currency],
        );

        return $request;
    }
}
