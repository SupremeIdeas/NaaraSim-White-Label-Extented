<?php

namespace App\Services\Payouts\Hardening;

use App\Models\PayoutAccountingEntry;
use App\Models\PayoutRequest;
use App\Support\Money4;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The payout accounting ledger (Addendum D-3.11): every payout posts BALANCED double-entry lines
 * (Σ debits = Σ credits per group), append-only, idempotent per (event, request).
 *
 *   hold      Dr earnings_liability            Cr payout_in_transit      the user's balance is now owed to the payout
 *   paid      Dr payout_in_transit             Cr provider_float_{p}     what left the provider account
 *                                              Cr platform_fee_income    the fee we withheld from what was sent
 *   reversed  Dr payout_in_transit             Cr earnings_liability     the hold went back to the user
 *   returned  reverses the paid group, then the hold (net: the money is back in the bucket)
 *   fees      Dr provider_fees                 Cr provider_float_{p}     posted by settlement reconciliation
 *
 * Amounts are integer 1/10000-USD units (Money4) — no float arithmetic.
 */
class PayoutAccounting
{
    public const LIABILITY = 'earnings_liability';

    public const IN_TRANSIT = 'payout_in_transit';

    public const FEE_INCOME = 'platform_fee_income';

    public const PROVIDER_FEES = 'provider_fees';

    public static function floatAccount(?string $provider): string
    {
        return 'provider_float_'.($provider ?: 'unknown');
    }

    /** USD this request is worth, or null when it cannot be established (such rows are reported, not guessed). */
    public function usd(PayoutRequest $r): ?string
    {
        if ($r->usd_amount !== null) {
            return (string) $r->usd_amount;
        }
        if ($r->source_bucket !== 'referral_credits' && $r->credit_amount !== null) {
            return (string) $r->credit_amount;
        }

        return strtoupper((string) $r->currency) === 'USD' ? (string) $r->amount : null;
    }

    public function postHold(PayoutRequest $r): void
    {
        $this->post($r, 'hold', [[self::LIABILITY, 'debit', 1], [self::IN_TRANSIT, 'credit', 1]], 'Funds held for payout');
    }

    public function postPaid(PayoutRequest $r): void
    {
        $fee = Money4::units((string) ($r->platform_fee_usd ?? '0'));
        $usd = $this->usd($r);
        if ($usd === null) {
            return;
        }
        $total = Money4::units($usd);
        $lines = [[self::IN_TRANSIT, 'debit', $total], [self::floatAccount($r->provider), 'credit', $total - $fee]];
        if ($fee > 0) {
            $lines[] = [self::FEE_INCOME, 'credit', $fee];
        }
        $this->postUnits($r, 'paid', $lines, 'Payout delivered');
    }

    public function postReversal(PayoutRequest $r): void
    {
        $this->post($r, 'reversed', [[self::IN_TRANSIT, 'debit', 1], [self::LIABILITY, 'credit', 1]], 'Hold returned to the user');
    }

    /** Money came back after we reported it paid: undo the paid group exactly, then the hold. */
    public function postReturned(PayoutRequest $r): void
    {
        $fee = Money4::units((string) ($r->platform_fee_usd ?? '0'));
        $usd = $this->usd($r);
        if ($usd === null) {
            return;
        }
        $total = Money4::units($usd);
        $lines = [[self::floatAccount($r->provider), 'debit', $total - $fee], [self::IN_TRANSIT, 'credit', $total]];
        if ($fee > 0) {
            $lines[] = [self::FEE_INCOME, 'debit', $fee];
        }
        $this->postUnits($r, 'returned', $lines, 'Payout returned by the bank/provider');
        $this->post($r, 'returned_hold', [[self::IN_TRANSIT, 'debit', 1], [self::LIABILITY, 'credit', 1]], 'Returned funds re-credited');
    }

    /** A provider fee discovered at settlement reconciliation. */
    public function postProviderFee(string $provider, string $feeUsd, string $postingKey, ?int $requestId = null, ?string $memo = null): void
    {
        $units = Money4::units($feeUsd);
        if ($units <= 0) {
            return;
        }
        $this->write(null, $requestId, $postingKey, [[self::PROVIDER_FEES, 'debit', $units], [self::floatAccount($provider), 'credit', $units]], $memo ?? 'Provider fee', null);
    }

    /** @param list<array{0:string,1:string,2:int}> $lines  amount 1 = "the request's full USD" */
    private function post(PayoutRequest $r, string $event, array $lines, string $memo): void
    {
        $usd = $this->usd($r);
        if ($usd === null) {
            return;
        }
        $units = Money4::units($usd);
        $lines = array_map(fn ($l) => [$l[0], $l[1], $l[2] === 1 ? $units : $l[2]], $lines);
        $this->postUnits($r, $event, $lines, $memo);
    }

    private function postUnits(PayoutRequest $r, string $event, array $lines, string $memo): void
    {
        $this->write($r, $r->id, $event.':'.$r->id, $lines, $memo, $r);
    }

    /** One group, all-or-nothing, skipped entirely if this (event, request) was already posted. */
    private function write(?PayoutRequest $r, ?int $requestId, string $key, array $lines, string $memo, ?PayoutRequest $req = null): void
    {
        $debit = array_sum(array_map(fn ($l) => $l[1] === 'debit' ? $l[2] : 0, $lines));
        $credit = array_sum(array_map(fn ($l) => $l[1] === 'credit' ? $l[2] : 0, $lines));
        if ($debit !== $credit || $debit <= 0) {
            throw new \LogicException("Unbalanced payout posting {$key}: {$debit} vs {$credit}");
        }
        if (PayoutAccountingEntry::where('posting_key', $key.':0')->exists()) {
            return;
        }

        $group = (string) Str::uuid();
        DB::transaction(function () use ($lines, $group, $requestId, $key, $memo, $req) {
            foreach ($lines as $i => [$code, $dir, $units]) {
                PayoutAccountingEntry::create([
                    'entry_group' => $group, 'payout_request_id' => $requestId, 'account_code' => $code, 'direction' => $dir,
                    'amount_usd' => Money4::str($units), 'currency' => $req?->currency ? strtoupper($req->currency) : null,
                    'amount_local' => $req?->amount, 'fx_rate' => $req?->fx_rate, 'occurred_at' => now(), 'memo' => $memo,
                    'posting_key' => $key.':'.$i,
                ]);
            }
        });
    }
}
