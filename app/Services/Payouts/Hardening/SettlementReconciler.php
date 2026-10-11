<?php

namespace App\Services\Payouts\Hardening;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutReconciliationItem;
use App\Models\PayoutReconciliationRun;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutException;
use App\Support\Auditor;
use App\Support\PayoutMoney;
use Illuminate\Support\Carbon;

/**
 * Settlement reconciliation (Addendum D-3.12): our payout records against the provider's own statement.
 * The statement arrives as CSV (an admin upload or a provider export) with the columns
 * `reference, amount, currency` and optionally `fee, status, date`. Nothing is ever auto-fixed:
 *
 *   matched              we know it, amounts agree
 *   amount_mismatch      we know it, amounts differ
 *   missing_in_system    the provider paid something we have no record of      (S2 alert)
 *   missing_at_provider  we say we paid/sent it, the statement has no line     (S2 alert)
 *   fee_unrecorded       matched, and the provider charged a fee we had not booked; the fee IS posted to the
 *                        accounting ledger (idempotently) and listed here for the owner
 *
 * An admin resolves flagged items with a note. Automatic API pulls are intentionally absent until each
 * provider's statement endpoint is confirmed from its real documentation.
 */
class SettlementReconciler
{
    public function __construct(private readonly PayoutAccounting $accounting) {}

    public function reconcileCsv(string $provider, string $csv, Carbon $from, Carbon $to, ?User $by = null, string $source = 'csv'): PayoutReconciliationRun
    {
        $rows = $this->parse($csv);
        $run = PayoutReconciliationRun::create([
            'provider' => $provider, 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString(),
            'source' => $source, 'created_by' => $by?->id, 'created_at' => now(),
        ]);

        $seen = [];
        $matched = $flagged = 0;
        foreach ($rows as $row) {
            $req = $this->find($provider, $row['reference']);
            $seen[$row['reference']] = true;
            if ($req === null) {
                $this->item($run, PayoutReconciliationItem::MISSING_IN_SYSTEM, null, $row);
                $flagged++;

                continue;
            }
            $seen[$req->wireReference()] = $seen[$req->reference] = true;

            $ours = PayoutMoney::round((float) $req->amount, (string) $req->currency);
            $theirs = PayoutMoney::round((float) $row['amount'], $row['currency'] ?: (string) $req->currency);
            $tolerance = PayoutMoney::minorUnit((string) $req->currency) / 2;
            if (abs($ours - $theirs) > $tolerance || ($row['currency'] !== '' && strtoupper($row['currency']) !== strtoupper((string) $req->currency))) {
                $this->item($run, PayoutReconciliationItem::AMOUNT_MISMATCH, $req, $row, $ours);
                $flagged++;

                continue;
            }

            $matched++;
            $this->item($run, PayoutReconciliationItem::MATCHED, $req, $row, $ours);
            if ($row['fee'] !== null && (float) $row['fee'] > 0 && $this->bookFee($provider, $req, (float) $row['fee'], $row['reference'])) {
                $this->item($run, PayoutReconciliationItem::FEE_UNRECORDED, $req, $row, $ours);
            }
        }

        // Anything we believe left the building in the period that the statement never mentions.
        PayoutRequest::query()->where('provider', $provider)->whereIn('status', [PayoutRequest::PAID, PayoutRequest::PROCESSING])
            ->whereBetween('updated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get()
            ->each(function (PayoutRequest $r) use ($run, $seen, &$flagged) {
                if (! isset($seen[$r->wireReference()]) && ! isset($seen[$r->reference]) && ! isset($seen[(string) $r->provider_ref])) {
                    $this->item($run, PayoutReconciliationItem::MISSING_AT_PROVIDER, $r, ['reference' => $r->wireReference(), 'amount' => null, 'currency' => $r->currency, 'fee' => null], PayoutMoney::round((float) $r->amount, (string) $r->currency));
                    $flagged++;
                }
            });

        $run->update(['matched' => $matched, 'flagged' => $flagged]);
        if ($flagged > 0) {
            AlertAdminJob::dispatch(
                code: 'settlement_mismatch',
                message: "Settlement reconciliation for {$provider} ({$from->toDateString()} to {$to->toDateString()}) found {$flagged} item(s) to review. Nothing was changed automatically.",
                context: ['run_id' => $run->id, 'provider' => $provider],
            );
        }

        return $run;
    }

    /** An admin has looked at a flagged item. A note is required; the action is audited. */
    public function resolve(PayoutReconciliationItem $item, User $admin, string $note): void
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);
        if (trim($note) === '') {
            throw new PayoutException('A resolution note is required.');
        }
        $item->forceFill(['resolved_at' => now(), 'resolved_by' => $admin->id, 'resolution_note' => $note])->save();
        Auditor::log('payout.reconciliation_resolved', 'PayoutReconciliationItem', $item->id, ['by' => $admin->id, 'note' => $note, 'kind' => $item->kind]);
    }

    /** @return list<array{reference:string, amount:string, currency:string, fee:?string, status:string}> */
    private function parse(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv((string) array_shift($lines)));
        foreach (['reference', 'amount'] as $need) {
            if (! in_array($need, $header, true)) {
                throw new PayoutException("The statement needs a '{$need}' column.");
            }
        }
        $out = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_combine($header, array_pad(str_getcsv($line), count($header), '')) ?: [];
            $ref = trim((string) ($cells['reference'] ?? ''));
            if ($ref === '') {
                continue;
            }
            $out[] = [
                'reference' => $ref, 'amount' => (string) ($cells['amount'] ?? '0'), 'currency' => strtoupper(trim((string) ($cells['currency'] ?? ''))),
                'fee' => isset($cells['fee']) && trim($cells['fee']) !== '' ? $cells['fee'] : null, 'status' => strtolower(trim((string) ($cells['status'] ?? ''))),
            ];
        }

        return $out;
    }

    private function find(string $provider, string $reference): ?PayoutRequest
    {
        return PayoutRequest::query()->where('provider', $provider)
            ->where(fn ($q) => $q->where('provider_reference', $reference)->orWhere('reference', $reference)->orWhere('provider_ref', $reference))
            ->first();
    }

    private function item(PayoutReconciliationRun $run, string $kind, ?PayoutRequest $req, array $row, ?float $ours = null): void
    {
        PayoutReconciliationItem::create([
            'run_id' => $run->id, 'kind' => $kind, 'payout_request_id' => $req?->id, 'provider_reference' => $row['reference'],
            'our_amount' => $ours, 'provider_amount' => $row['amount'] ?? null, 'provider_fee' => $row['fee'] ?? null,
            'currency' => strtoupper((string) ($row['currency'] ?: $req?->currency)) ?: null,
        ]);
    }

    /** Post the provider's fee once (idempotent). Returns true if it was newly booked. */
    private function bookFee(string $provider, PayoutRequest $req, float $feeLocal, string $reference): bool
    {
        $rate = (float) $req->fx_rate;
        if ($rate <= 0) {
            return false; // cannot convert honestly; the item is still listed as matched
        }
        $key = "fee:{$provider}:{$req->id}:0";
        if (\App\Models\PayoutAccountingEntry::where('posting_key', $key)->exists()) {
            return false;
        }
        $this->accounting->postProviderFee($provider, number_format($feeLocal / $rate, 4, '.', ''), "fee:{$provider}:{$req->id}", $req->id, "Provider fee on {$reference}");

        return true;
    }
}
