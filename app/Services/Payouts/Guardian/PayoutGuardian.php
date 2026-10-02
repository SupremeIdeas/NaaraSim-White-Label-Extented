<?php

namespace App\Services\Payouts\Guardian;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutDecision;
use App\Models\PayoutRequest;
use App\Models\PayoutTrustProfile;
use App\Services\Payouts\Guardian\Gates\AccountIntegrityGate;
use App\Services\Payouts\Guardian\Gates\CoolingOffGate;
use App\Services\Payouts\Guardian\Gates\DestinationSharingGate;
use App\Services\Payouts\Guardian\Gates\FundingGate;
use App\Services\Payouts\Guardian\Gates\FxQuoteGate;
use App\Services\Payouts\Guardian\Gates\Gate;
use App\Services\Payouts\Guardian\Gates\KycAndLimitsGate;
use App\Services\Payouts\Guardian\Gates\LedgerHoldGate;
use App\Services\Payouts\Guardian\Gates\NameMatchGate;
use App\Services\Payouts\Guardian\Gates\SwitchesAndHealthGate;
use App\Services\Payouts\PayoutService;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The Payout Guardian (Addendum C): reads one pending request, runs the nine hard
 * gates and the soft risk signals, and reaches ONE decision —
 * approve | defer | hold | reject — recorded immutably in payout_decisions.
 *
 * Evaluation is read-only and makes no external HTTP call. The only mutation is
 * routing the request (and, when it is allowed to approve, one atomic
 * pending→approved compare-and-set). It is allowed to approve ONLY when every one
 * of these holds: auto-approval ON, shadow mode OFF, mode `auto`, and the provider's
 * own auto-approve switch ON. Otherwise it is advisory: the decision is logged with
 * `shadow=true` and the request goes to the admin queue exactly as before.
 *
 * Anything unexpected throws — and the job turns that into human review, never into
 * an approval.
 */
class PayoutGuardian
{
    public const ENGINE_VERSION = '1';

    public function __construct(
        private PayoutService $payouts,
        private RiskScorer $scorer,
        private GuardianBreakers $breakers,
    ) {}

    /** @return list<Gate> */
    public function gates(): array
    {
        return [
            app(SwitchesAndHealthGate::class), app(AccountIntegrityGate::class), app(LedgerHoldGate::class),
            app(KycAndLimitsGate::class), app(CoolingOffGate::class), app(DestinationSharingGate::class),
            app(NameMatchGate::class), app(FxQuoteGate::class), app(FundingGate::class),
        ];
    }

    /** Whether this evaluation may actually change a request's fate (vs. advise only). */
    public function acting(?string $provider): bool
    {
        return PayoutSettings::autoApprovalEnabled() && ! PayoutSettings::shadowMode() && PayoutSettings::autopilot()
            && PayoutSettings::providerAutoApprove($provider);
    }

    public function evaluate(PayoutRequest $request): ?PayoutDecision
    {
        $request->refresh();
        if ($request->status !== PayoutRequest::PENDING) {
            return null; // already actioned (admin approved/rejected, or sent)
        }

        $ctx = new GuardianContext($request);

        $results = [];
        foreach ($this->gates() as $gate) {
            $results[] = $gate->check($ctx);
        }
        $signals = $this->scorer->signals($ctx);
        $all = array_merge($results, $signals);
        $score = max(0, min(100, array_sum(array_map(fn (GateResult $g) => $g->weight, $all))));

        [$decision, $reason, $nextCheck] = $this->decide($ctx, $results, $score);

        $acting = $this->acting($request->provider);
        $qa = false;
        if ($decision === 'approve' && $acting) {
            $breaker = $this->breakers->tripped($ctx->usd() ?? 0.0);
            if ($breaker !== null) {
                $decision = 'hold';
                $reason = $breaker['code'];
                $all[] = GateResult::fail('breaker', GateResult::HOLD, $breaker['code'], $breaker['evidence']);
                $this->alertOnce($breaker['code'], "Payout Guardian breaker tripped ({$breaker['code']}): auto-approvals are paused for review.", $breaker['evidence']);
            } else {
                $qa = (mt_rand(0, 9999) / 100) < PayoutSettings::qaSamplePct();
            }
        } elseif ($decision === 'approve' && PayoutSettings::autoApprovalEnabled() && ! PayoutSettings::shadowMode() && PayoutSettings::autopilot()) {
            // Would approve, but this rail's own switch is off: say so, truthfully.
            $decision = 'hold';
            $reason = 'provider_auto_approve_off';
        }

        $record = DB::transaction(fn () => PayoutDecision::create([
            'payout_request_id' => $request->id,
            'attempt' => (int) PayoutDecision::where('payout_request_id', $request->id)->max('attempt') + 1,
            'decision' => $decision,
            'reason' => $reason,
            'score' => $score,
            'rules' => array_map(fn (GateResult $g) => $g->toArray(), $all),
            'engine_version' => self::ENGINE_VERSION,
            'shadow' => ! $acting,
            'qa_sampled' => $qa,
            'decided_by' => 'system',
            'decided_at' => now(),
            'next_check_at' => $nextCheck,
        ]));

        $request->forceFill(['risk_score' => $score, 'evaluating_at' => null])->save();

        $acting ? $this->apply($request, $record, $reason, $nextCheck) : $this->advise($request, $record, $reason);

        foreach ($results as $g) {
            if ($g->id === 'G3_ledger_hold' && $g->failed() && $g->reason === 'hold_mismatch') {
                AlertAdminJob::dispatch(
                    code: 'payout_hold_mismatch',
                    message: "Payout #{$request->id}: the source ledger does not match the request ({$g->evidence['status']}). Do not approve until checked.",
                    context: ['payout_id' => $request->id] + $g->evidence,
                );
            }
        }

        return $record;
    }

    /** @return array{0: string, 1: string, 2: ?\Carbon\CarbonInterface} */
    private function decide(GuardianContext $ctx, array $results, int $score): array
    {
        $failed = array_values(array_filter($results, fn (GateResult $g) => $g->failed()));
        if ($failed !== []) {
            foreach ([GateResult::REJECT, GateResult::HOLD, GateResult::DEFER] as $severity) {
                $hits = array_values(array_filter($failed, fn (GateResult $g) => $g->outcome === $severity));
                if ($hits !== []) {
                    $next = $severity === GateResult::DEFER
                        ? collect($hits)->map(fn (GateResult $g) => $g->nextCheckAt)->filter()->max()
                        : null;

                    return [$severity, $hits[0]->reason, $severity === GateResult::DEFER ? ($next ?? now()->addMinutes(10)) : null];
                }
            }
        }

        $usd = $ctx->usd();
        if ($usd === null) {
            return ['hold', 'usd_unknown', null];
        }

        $tier = PayoutTrustProfile::find($ctx->request->user_id)?->tier ?? 'new';
        $limit = PayoutSettings::tierLimitUsd($tier);

        if ($score >= PayoutSettings::bandHold()) {
            return ['hold', 'risk_high', null];
        }
        if ($usd > $limit) {
            return ['hold', 'over_tier_limit', null];
        }
        if ($score >= PayoutSettings::bandApprove() && ! ($tier !== 'new' && $usd <= $limit / 2)) {
            return ['hold', 'risk_medium', null];
        }

        return ['approve', 'passed', null];
    }

    /** Acting mode: carry the decision out. */
    private function apply(PayoutRequest $request, PayoutDecision $d, string $reason, $nextCheck): void
    {
        switch ($d->decision) {
            case 'approve':
                $this->payouts->approveBySystem($request, $d);
                break;
            case 'reject':
                $this->payouts->rejectBySystem($request, $this->safeReason($reason), $d);
                break;
            case 'defer':
                $wasDeferred = $request->review_state === PayoutRequest::REVIEW_DEFERRED && $request->hold_reason === $reason;
                $request->forceFill([
                    'review_state' => PayoutRequest::REVIEW_DEFERRED, 'hold_reason' => $reason, 'next_check_at' => $nextCheck,
                ])->save();
                $wasDeferred || $this->tellUser($request); // once per distinct deferral, not every re-check
                break;
            default: // hold
                $request->forceFill(['review_state' => PayoutRequest::REVIEW_MANUAL, 'hold_reason' => $reason, 'next_check_at' => null])->save();
                $this->tellUser($request);
        }
    }

    private function tellUser(PayoutRequest $request): void
    {
        $request->user?->notify(new \App\Notifications\PayoutStatusNotification($request->id));
    }

    /** Advisory mode: change nothing about the money path — park it in the admin queue with the Guardian's opinion. */
    private function advise(PayoutRequest $request, PayoutDecision $d, string $reason): void
    {
        $request->forceFill([
            'review_state' => PayoutRequest::REVIEW_MANUAL,
            'hold_reason' => $d->decision === 'approve' ? 'advisory_approve' : $reason,
            'next_check_at' => null,
        ])->save();
    }

    /** What a user may be told when the Guardian declines — never a rule name or score. */
    private function safeReason(string $reason): string
    {
        return match ($reason) {
            'cap_exceeded' => 'This withdrawal is over your current limit. Please try again later or contact support.',
            default => 'This withdrawal could not be processed. Please contact support.',
        };
    }

    private function alertOnce(string $code, string $message, array $context): void
    {
        if (Cache::add("guardian-alert:{$code}", 1, now()->addHour())) {
            AlertAdminJob::dispatch(code: 'guardian_breaker_tripped', message: $message, context: $context);
        }
    }
}
