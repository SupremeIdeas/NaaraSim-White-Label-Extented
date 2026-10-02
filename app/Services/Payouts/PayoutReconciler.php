<?php

namespace App\Services\Payouts;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutProviderCall;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Support\Auditor;
use App\Support\PayoutSettings;
use Throwable;

/**
 * Resolves UNKNOWN provider outcomes (Addendum C fix A) — a transfer we submitted
 * whose result we never saw (timeout, 5xx, worker died). The rule that makes this
 * safe: we only ever conclude from the PROVIDER's own answer.
 *
 *   found paid/failed → apply it through the normal idempotent confirm()/fail()
 *   found processing  → provider has it; the webhook will finish the job
 *   not found         → needs 3 consecutive "not found" answers spread across the
 *                       provider grace window BEFORE we reverse (one miss can be lag)
 *   unsupported / exhausted lookups → a HUMAN decides ("cannot verify, do not refund blindly")
 *
 * A verified webhook arriving at any time resolves the request first (PayoutService
 * closes the open calls when it finalises).
 */
class PayoutReconciler
{
    public const NOT_FOUND_REQUIRED = 3;

    private const MAX_LOOKUP_ERRORS = 12;

    public function __construct(private readonly PayoutService $payouts) {}

    /** @return array{resolved:int, waiting:int, manual:int} */
    public function reconcileDue(int $limit = 100): array
    {
        $stats = ['resolved' => 0, 'waiting' => 0, 'manual' => 0];

        PayoutProviderCall::query()
            ->where('state', PayoutProviderCall::UNKNOWN)
            ->whereNotNull('next_lookup_at')
            ->where('next_lookup_at', '<=', now())
            ->orderBy('next_lookup_at')
            ->limit($limit)
            ->get()
            ->each(function (PayoutProviderCall $call) use (&$stats) {
                $outcome = $this->reconcile($call);
                $stats[$outcome]++;
            });

        return $stats;
    }

    /** @return 'resolved'|'waiting'|'manual' */
    public function reconcile(PayoutProviderCall $call): string
    {
        $request = $call->request()->first();

        // A webhook (or an admin) already finished it.
        if ($request === null || $request->isFinal()) {
            $call->forceFill([
                'state' => $request?->status === PayoutRequest::PAID ? PayoutProviderCall::CONFIRMED : PayoutProviderCall::DEFINITIVELY_FAILED,
                'finished_at' => now(), 'next_lookup_at' => null,
            ])->save();

            return 'resolved';
        }

        $gateway = $this->payouts->gatewayFor($call->provider);
        if (! $gateway instanceof SupportsStatusLookup) {
            return $this->toManualReview($call, $request, 'lookup_unsupported', 'The provider offers no verified status lookup');
        }

        $call->increment('lookup_attempts');

        try {
            $result = $gateway->lookupTransfer($request);
        } catch (Throwable $e) {
            if ($call->lookup_attempts >= self::MAX_LOOKUP_ERRORS) {
                return $this->toManualReview($call, $request, 'lookup_failed', 'Provider lookups keep failing: '.$e->getMessage());
            }
            $call->forceFill(['next_lookup_at' => now()->addMinutes(min(30, 2 * $call->lookup_attempts))])->save();

            return 'waiting';
        }

        return match ($result->state) {
            LookupResult::UNSUPPORTED => $this->toManualReview($call, $request, 'lookup_unsupported', 'The provider offers no verified status lookup'),
            LookupResult::NOT_FOUND => $this->onNotFound($call, $request),
            default => $this->onFound($call, $request, $result),
        };
    }

    private function onFound(PayoutProviderCall $call, PayoutRequest $request, LookupResult $result): string
    {
        $call->forceFill(['not_found_count' => 0, 'first_not_found_at' => null])->save();

        if ($result->status === 'paid') {
            $this->payouts->confirm($request, $result->providerRef);

            return 'resolved';
        }

        if ($result->status === 'failed') {
            $this->payouts->fail($request, 'Provider reported: '.($result->failureReason ?? 'failed'));

            return 'resolved';
        }

        // Provider has it in flight — the webhook completes it. Stop polling as "unknown".
        $call->forceFill([
            'state' => PayoutProviderCall::CONFIRMED, 'provider_ref' => $result->providerRef ?? $call->provider_ref,
            'finished_at' => now(), 'next_lookup_at' => null,
        ])->save();
        $request->forceFill(['provider_ref' => $result->providerRef ?? $request->provider_ref])->save();

        return 'resolved';
    }

    private function onNotFound(PayoutProviderCall $call, PayoutRequest $request): string
    {
        $call->forceFill([
            'not_found_count' => $call->not_found_count + 1,
            'first_not_found_at' => $call->first_not_found_at ?? now(),
        ])->save();

        $grace = PayoutSettings::lookupGraceMinutes();
        $elapsed = $call->first_not_found_at->diffInMinutes(now());

        if ($call->not_found_count >= self::NOT_FOUND_REQUIRED && $elapsed >= $grace) {
            $call->forceFill([
                'state' => PayoutProviderCall::DEFINITIVELY_FAILED, 'finished_at' => now(), 'next_lookup_at' => null,
                'error' => 'Provider has no record of this transfer after '.$call->not_found_count.' lookups over '.$elapsed.' minutes.',
            ])->save();
            $this->payouts->fail($request, 'Provider has no record of the transfer.');

            return 'resolved';
        }

        // Spread the three required misses across the grace window.
        $call->forceFill(['next_lookup_at' => now()->addMinutes(max(1, intdiv($grace, self::NOT_FOUND_REQUIRED)))])->save();

        return 'waiting';
    }

    private function toManualReview(PayoutProviderCall $call, PayoutRequest $request, string $code, string $why): string
    {
        $call->forceFill(['next_lookup_at' => null])->save(); // stop re-picking it

        if ($request->review_state !== PayoutRequest::REVIEW_MANUAL || $request->hold_reason !== 'unknown_outcome') {
            $request->forceFill(['review_state' => PayoutRequest::REVIEW_MANUAL, 'hold_reason' => 'unknown_outcome'])->save();
            AlertAdminJob::dispatch(
                code: 'payout_unknown_outcome',
                message: "Payout #{$request->id} was submitted to {$call->provider} but its outcome is unknown and cannot be verified automatically ({$why}). Do NOT refund blindly — check the provider dashboard and resolve it.",
                context: ['payout_id' => $request->id, 'provider' => $call->provider, 'reason' => $code],
            );
        }

        return 'manual';
    }

    /**
     * An admin, having checked the provider's own dashboard, resolves an unknown
     * outcome. A note is REQUIRED and the action is audited.
     */
    public function adminResolve(PayoutRequest $request, User $admin, string $outcome, string $note): PayoutRequest
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']) || $admin->can('payouts.review'), 403);
        if (trim($note) === '') {
            throw new PayoutException('A note with the evidence is required.');
        }
        if (! in_array($outcome, ['paid', 'failed'], true)) {
            throw new PayoutException('Outcome must be paid or failed.');
        }

        Auditor::log('payout.admin_resolved_unknown', 'PayoutRequest', $request->id, ['outcome' => $outcome, 'note' => $note, 'by' => $admin->id]);

        return $outcome === 'paid'
            ? $this->payouts->confirm($request, $request->provider_ref)
            : $this->payouts->fail($request, 'Admin-resolved: '.$note);
    }
}
