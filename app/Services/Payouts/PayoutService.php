<?php

namespace App\Services\Payouts;

use App\Events\PayoutReversed;
use App\Events\PayoutSettled;
use App\Jobs\AlertAdminJob;
use App\Jobs\SendPayoutJob;
use App\Models\PaymentCharge;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Support\Facades\Cache;
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
    public function __construct(private array $gateways) {}

    public function gatewayFor(?string $provider): ?PayoutGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->name() === $provider && $gateway->available()) {
                return $gateway;
            }
        }

        return null;
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
     * was PSP-verified.
     *
     * @throws PayoutException
     */
    public function createRequest(
        User $payee,
        float $amount,
        string $currency,
        string $sourceBucket,
        PayoutAccount $account,
        string $reference,
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

        return DB::transaction(function () use ($payee, $amount, $currency, $sourceBucket, $account, $reference) {
            $existing = PayoutRequest::query()->where('reference', $reference)->first();
            if ($existing !== null) {
                return $existing; // idempotent — never a second payout for the same reference
            }

            $request = PayoutRequest::create([
                'user_id' => $payee->id,
                'payout_account_id' => $account->id,
                'amount' => round($amount, 4),
                'currency' => strtoupper($currency),
                'source_bucket' => $sourceBucket,
                'status' => PayoutRequest::PENDING,
                'provider' => $account->provider,
                'reference' => $reference,
            ]);

            Auditor::log('payout.requested', 'PayoutRequest', $request->id, [
                'amount' => $request->amount, 'currency' => $request->currency, 'source' => $sourceBucket,
            ]);

            return $request;
        });
    }

    /**
     * Admin approves a pending request (manual mode) and queues the transfer.
     * The external PSP call always runs in a job (money-safety rule 8).
     */
    public function approve(PayoutRequest $request, User $approver): PayoutRequest
    {
        abort_unless($approver->hasAnyRole(['super_admin', 'admin']), 403);

        if ($request->status !== PayoutRequest::PENDING) {
            return $request; // already actioned — no double approval
        }

        $request->forceFill([
            'status' => PayoutRequest::APPROVED,
            'approved_by' => $approver->id,
        ])->save();
        Auditor::log('payout.approved', 'PayoutRequest', $request->id, ['approved_by' => $approver->id]);

        SendPayoutJob::dispatch($request->id);

        return $request->refresh();
    }

    /**
     * Perform the transfer. Row-locked and safe to call once; refuses any state
     * that isn't pending/approved so it can never re-send.
     */
    public function send(PayoutRequest $request): PayoutRequest
    {
        return DB::transaction(function () use ($request) {
            /** @var PayoutRequest $fresh */
            $fresh = PayoutRequest::query()->whereKey($request->id)->lockForUpdate()->first();
            if (! in_array($fresh->status, [PayoutRequest::PENDING, PayoutRequest::APPROVED], true)) {
                return $fresh; // already sent/finalised
            }

            $account = $fresh->account;
            $gateway = $this->gatewayFor($fresh->provider);
            if ($account === null || $gateway === null) {
                return $this->finalizeFailure($fresh, 'No available payout provider for this account.');
            }

            try {
                if (empty($account->provider_recipient_ref)) {
                    $account->forceFill(['provider_recipient_ref' => $gateway->createRecipient($account)])->save();
                }
                $result = $gateway->sendTransfer($fresh, $account);
            } catch (Throwable $e) {
                return $this->finalizeFailure($fresh, 'Transfer error: '.$e->getMessage());
            }

            if ($result->status === 'failed') {
                return $this->finalizeFailure($fresh, $result->failureReason ?? 'Transfer rejected by provider.');
            }

            $fresh->forceFill([
                'status' => $result->status === 'paid' ? PayoutRequest::PAID : PayoutRequest::PROCESSING,
                'provider_ref' => $result->providerRef,
                'settled_at' => $result->status === 'paid' ? now() : null,
            ])->save();

            if ($result->status === 'paid') {
                PayoutSettled::dispatch($fresh);
            }

            return $fresh;
        });
    }

    /**
     * Admin declines a pending request before it's sent. Reverses the hold (the
     * source layer returns the funds) — nothing was ever sent to the PSP.
     */
    public function reject(PayoutRequest $request, User $approver, string $reason = 'Declined by admin'): PayoutRequest
    {
        abort_unless($approver->hasAnyRole(['super_admin', 'admin']), 403);

        if ($request->status !== PayoutRequest::PENDING) {
            return $request; // only a not-yet-sent request can be declined here
        }

        $request->forceFill([
            'status' => PayoutRequest::REVERSED,
            'failure_reason' => $reason,
            'approved_by' => $approver->id,
        ])->save();
        Auditor::log('payout.rejected', 'PayoutRequest', $request->id, ['by' => $approver->id, 'reason' => $reason]);

        PayoutReversed::dispatch($request);

        return $request;
    }

    /** Apply a verified webhook event to its request (idempotent). */
    public function applyWebhook(PayoutEvent $event): ?PayoutRequest
    {
        $request = PayoutRequest::query()->where('reference', $event->reference)->first();
        if ($request === null) {
            return null;
        }

        return match ($event->status) {
            'paid' => $this->confirm($request, $event->providerRef),
            'failed', 'reversed' => $this->fail($request, 'Provider reported: '.$event->status),
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
        Auditor::log('payout.paid', 'PayoutRequest', $request->id);
        PayoutSettled::dispatch($request);

        return $request;
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

    private function finalizeFailure(PayoutRequest $request, string $reason): PayoutRequest
    {
        $request->forceFill([
            'status' => PayoutRequest::FAILED,
            'failure_reason' => $reason,
        ])->save();
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
