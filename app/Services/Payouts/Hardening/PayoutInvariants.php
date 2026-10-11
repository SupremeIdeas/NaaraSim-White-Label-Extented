<?php

namespace App\Services\Payouts\Hardening;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutAccountingEntry;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutFloatMovement;
use App\Models\PayoutInvariantRun;
use App\Models\PayoutProviderCall;
use App\Models\PayoutRequest;
use App\Services\Payouts\Guardian\HoldCheck;
use App\Services\Payouts\Guardian\LedgerHoldVerifier;
use App\Support\PayoutSettings;

/**
 * The read-only money-invariants checker (Addendum D-3.8). It never changes a ledger — it proves
 * (or disproves) that the books agree with the payout state machine, and shouts when they do not.
 *
 *  1  paid            ⇒ exactly one consumed hold, no release            (zero tolerance)
 *  2  failed/reversed ⇒ the hold was released                            (zero tolerance)
 *  3  open request    ⇒ its hold exists, unreleased, right amount        (zero tolerance)
 *  4  references are unique per request and per provider                 (zero tolerance)
 *  5  provider-call states agree with the request, none stuck            (grace window)
 *  6  returned        ⇒ nets to zero against its paid payout
 *  7  float balance   ⇒ equals the last movement and the movements chain
 *  8  accounting      ⇒ every entry group balances; every paid payout has its postings
 */
class PayoutInvariants
{
    public const SAMPLE = 25; // offenders listed per check

    public function __construct(private readonly LedgerHoldVerifier $holds) {}

    public function run(string $triggeredBy = 'schedule', bool $alert = true): PayoutInvariantRun
    {
        $run = PayoutInvariantRun::create(['started_at' => now(), 'status' => 'running', 'triggered_by' => $triggeredBy]);

        $results = [
            $this->paidHasConsumedHold(),
            $this->failedHasReleasedHold(),
            $this->openHasLiveHold(),
            $this->referencesUnique(),
            $this->providerCallsAgree(),
            $this->returnedNetsToZero(),
            $this->floatChains(),
            $this->accountingBalanced(),
        ];

        $violations = (int) array_sum(array_map(fn ($r) => $r['count'], $results));
        $run->update([
            'finished_at' => now(), 'results' => $results, 'violation_count' => $violations,
            'status' => $violations === 0 ? 'ok' : 'violations',
        ]);

        if ($violations > 0 && $alert) {
            $names = implode(', ', array_map(fn ($r) => $r['name'], array_filter($results, fn ($r) => $r['count'] > 0)));
            AlertAdminJob::dispatch(
                code: 'invariant_violation',
                message: "Payout money invariants FAILED ({$violations} problem(s)): {$names}. Open Admin → Payouts → Health and resolve before anything else is sent.",
                context: ['run_id' => $run->id, 'violations' => $violations],
            );
        }

        return $run;
    }

    /** @return array{id:string,name:string,ok:bool,count:int,offenders:list<array<string,mixed>>} */
    private function result(string $id, string $name, array $offenders, ?int $count = null): array
    {
        $count ??= count($offenders);

        return ['id' => $id, 'name' => $name, 'ok' => $count === 0, 'count' => $count, 'offenders' => array_slice($offenders, 0, self::SAMPLE)];
    }

    private function paidHasConsumedHold(): array
    {
        $bad = [];
        PayoutRequest::query()->where('status', PayoutRequest::PAID)->orderBy('id')->chunkById(500, function ($rows) use (&$bad) {
            foreach ($rows as $r) {
                $c = $this->holds->verify($r);
                if (! in_array($c->status, [HoldCheck::OK, HoldCheck::NEGATIVE, HoldCheck::UNVERIFIABLE], true)) {
                    $bad[] = ['request_id' => $r->id, 'bucket' => $r->source_bucket, 'problem' => $c->status];
                }
            }
        });

        return $this->result('paid_hold_consumed', 'Every paid payout has exactly one consumed hold', $bad);
    }

    private function failedHasReleasedHold(): array
    {
        $bad = [];
        PayoutRequest::query()->whereIn('status', [PayoutRequest::FAILED, PayoutRequest::REVERSED])->orderBy('id')->chunkById(500, function ($rows) use (&$bad) {
            foreach ($rows as $r) {
                if (! (float) $r->credit_amount) {
                    continue; // legacy row that never recorded a hold
                }
                $c = $this->holds->verify($r);
                if ($c->status === HoldCheck::OK || $c->status === HoldCheck::MISMATCH) {
                    $bad[] = ['request_id' => $r->id, 'bucket' => $r->source_bucket, 'problem' => 'hold_not_released'];
                }
            }
        });

        return $this->result('failed_hold_released', 'Every failed/reversed payout had its hold released', $bad);
    }

    private function openHasLiveHold(): array
    {
        $bad = [];
        PayoutRequest::query()->whereIn('status', PayoutRequest::OPEN)->orderBy('id')->chunkById(500, function ($rows) use (&$bad) {
            foreach ($rows as $r) {
                $c = $this->holds->verify($r);
                if (in_array($c->status, [HoldCheck::MISSING, HoldCheck::RELEASED, HoldCheck::MISMATCH], true)) {
                    $bad[] = ['request_id' => $r->id, 'bucket' => $r->source_bucket, 'problem' => $c->status];
                }
            }
        });

        return $this->result('open_hold_live', 'Every open payout still has its funds held', $bad);
    }

    private function referencesUnique(): array
    {
        $bad = [];
        foreach (PayoutRequest::query()->selectRaw('reference, COUNT(*) c')->groupBy('reference')->havingRaw('COUNT(*) > 1')->limit(self::SAMPLE)->get() as $d) {
            $bad[] = ['reference' => $d->reference, 'problem' => 'duplicate_reference'];
        }
        $dupes = PayoutRequest::query()->whereNotNull('provider_ref')->where('provider_ref', '!=', '')
            ->selectRaw('provider, provider_ref, COUNT(*) c')->groupBy('provider', 'provider_ref')->havingRaw('COUNT(*) > 1')->limit(self::SAMPLE)->get();
        foreach ($dupes as $d) {
            $bad[] = ['provider' => $d->provider, 'provider_ref' => $d->provider_ref, 'problem' => 'duplicate_provider_ref'];
        }

        return $this->result('references_unique', 'No two payouts share a reference', $bad);
    }

    private function providerCallsAgree(): array
    {
        $bad = [];
        $stuckBefore = now()->subMinutes(PayoutSettings::lookupGraceMinutes() + 60);

        PayoutProviderCall::query()->where('state', PayoutProviderCall::CONFIRMED)->with('request:id,status')->orderBy('id')->chunkById(500, function ($calls) use (&$bad) {
            foreach ($calls as $c) {
                $status = $c->request?->status;
                if (! in_array($status, [PayoutRequest::PROCESSING, PayoutRequest::PAID, PayoutRequest::RETURNED], true)) {
                    $bad[] = ['call_id' => $c->id, 'request_id' => $c->payout_request_id, 'problem' => 'confirmed_call_but_request_'.($status ?? 'missing')];
                }
            }
        });

        $stuck = PayoutProviderCall::query()
            ->whereIn('state', [PayoutProviderCall::INTENT, PayoutProviderCall::SUBMITTED, PayoutProviderCall::UNKNOWN])
            ->where('started_at', '<', $stuckBefore)
            ->limit(self::SAMPLE)->get();
        foreach ($stuck as $c) {
            $bad[] = ['call_id' => $c->id, 'request_id' => $c->payout_request_id, 'problem' => 'call_stuck_in_'.$c->state];
        }

        return $this->result('provider_calls_agree', 'Provider calls agree with their requests and none are stuck', $bad);
    }

    private function returnedNetsToZero(): array
    {
        $bad = [];
        PayoutRequest::query()->where('status', PayoutRequest::RETURNED)->orderBy('id')->chunkById(500, function ($rows) use (&$bad) {
            foreach ($rows as $r) {
                if (! \App\Services\Payouts\Hardening\ReturnedPayouts::nets($r)) {
                    $bad[] = ['request_id' => $r->id, 'problem' => 'return_credit_missing_or_unbalanced'];
                }
            }
        });

        return $this->result('returned_nets', 'Returned payouts net to zero against their payment', $bad);
    }

    private function floatChains(): array
    {
        $bad = [];
        foreach (PayoutFloatBalance::query()->get() as $b) {
            $moves = PayoutFloatMovement::query()->where('provider', $b->provider)->where('currency', $b->currency)->orderBy('id')->get();
            if ($moves->isEmpty()) {
                continue;
            }
            $running = null;
            foreach ($moves as $m) {
                if ($running !== null && $m->type !== 'sync' && abs(((float) $running + (float) $m->amount) - (float) $m->balance_after) > 0.0001) {
                    $bad[] = ['provider' => $b->provider, 'currency' => $b->currency, 'movement_id' => $m->id, 'problem' => 'movement_chain_broken'];
                    break;
                }
                $running = (float) $m->balance_after;
            }
            if (abs((float) $b->balance - (float) $moves->last()->balance_after) > 0.0001) {
                $bad[] = ['provider' => $b->provider, 'currency' => $b->currency, 'problem' => 'balance_differs_from_last_movement'];
            }
        }

        return $this->result('float_chain', 'Float balances equal their movements', $bad);
    }

    private function accountingBalanced(): array
    {
        $bad = [];
        $groups = PayoutAccountingEntry::query()
            ->selectRaw("entry_group, SUM(CASE WHEN direction = 'debit' THEN amount_usd ELSE 0 END) AS d, SUM(CASE WHEN direction = 'credit' THEN amount_usd ELSE 0 END) AS c")
            ->groupBy('entry_group')
            ->havingRaw("ABS(SUM(CASE WHEN direction = 'debit' THEN amount_usd ELSE -amount_usd END)) > 0.00005")
            ->limit(self::SAMPLE)->get();
        foreach ($groups as $g) {
            $bad[] = ['entry_group' => $g->entry_group, 'debits' => $g->d, 'credits' => $g->c, 'problem' => 'unbalanced_group'];
        }

        // A paid request whose USD value is known must have posted its `paid` lines.
        $missing = PayoutRequest::query()->where('status', PayoutRequest::PAID)
            ->where(fn ($q) => $q->whereNotNull('usd_amount')->orWhere('currency', 'USD'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payout_accounting_entries')->whereColumn('payout_accounting_entries.payout_request_id', 'payout_requests.id')->where('payout_accounting_entries.posting_key', 'like', 'paid:%'))
            ->limit(self::SAMPLE)->pluck('id');
        foreach ($missing as $id) {
            $bad[] = ['request_id' => $id, 'problem' => 'paid_without_accounting'];
        }

        return $this->result('accounting_balanced', 'Accounting entries balance and every paid payout is posted', $bad);
    }
}
