<?php

namespace App\Support;

use App\Models\OrderLog;
use App\Models\PaymentCharge;
use App\Models\PayoutRequest;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;

/**
 * Financial reconciliation (BUILD-5 §4). A single view of the platform's money
 * over a period, built on the real `wallet_transactions` ledger + the
 * gateway-attributed `payment_charges`, so an operator can catch "a webhook
 * silently failed and this much money is unaccounted for" before it becomes an
 * accounting problem — rather than trusting any single gateway's own log.
 *
 * This is ADMIN-ONLY internal reporting: unlike user-facing surfaces, provider
 * cost figures are allowed here (money rule 2 forbids cost only in USER-facing
 * output). All figures are USD unless labelled otherwise.
 */
class FinancialReconciliation
{
    /**
     * @return array{
     *   from:string, to:string,
     *   in_by_gateway: array<string,float>, total_in: float,
     *   wallet_credits: float, reconciliation_gap: float,
     *   wallet_by_type: array<string,float>,
     *   paid_out: float, pending_out: float,
     *   provider_cost: float, charged_to_user: float, gross_profit: float,
     *   outstanding_usd: float, outstanding_ngn: float (legacy-only, see note below)
     * }
     */
    public function report(Carbon $from, Carbon $to): array
    {
        // Money IN, per gateway, from the gateway-attributed inbound record.
        $inByGateway = PaymentCharge::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('gateway, SUM(amount) as vol')
            ->groupBy('gateway')
            ->pluck('vol', 'gateway')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
        $totalIn = round(array_sum($inByGateway), 2);

        // Wallet ledger movements by type over the period.
        $walletByType = WalletTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('type, SUM(amount) as vol')
            ->groupBy('type')
            ->pluck('vol', 'type')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();

        // Top-ups credited to wallets. The reconciliation signal: money the
        // gateways say came in vs. money the wallet ledger says it credited.
        // A meaningful gap points at a webhook that charged but never credited
        // (or a double credit) — the thing worth catching early.
        $walletCredits = round((float) ($walletByType['credit'] ?? 0), 2);

        // Money OUT — settled payouts vs. still-owed (a liability).
        $paidOut = round((float) PayoutRequest::query()
            ->where('status', PayoutRequest::PAID)
            ->whereBetween('settled_at', [$from, $to])
            ->sum('amount'), 2);
        $pendingOut = round((float) PayoutRequest::query()
            ->whereIn('status', [PayoutRequest::PENDING, PayoutRequest::APPROVED, PayoutRequest::PROCESSING])
            ->sum('amount'), 2);

        // Provider costs incurred (fulfilled orders) — cost, retail, margin.
        $orders = OrderLog::query()
            ->where('result', 'success')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('SUM(provider_cost) as cost, SUM(charged_to_user) as charged, SUM(profit) as profit')
            ->first();

        // Outstanding wallet liability RIGHT NOW (point-in-time, not the period)
        // — what the platform still owes users if everyone cashed out.
        // Unified USD Wallet (Part B): usd_balance is the ONE live, growing
        // liability figure. outstanding_ngn is legacy-only — no top-up credits
        // it anymore — and only ever shrinks as the migration backfill
        // (wallet:migrate-ngn-to-usd) or per-user conversion runs.
        $outstandingUsd = round((float) UserWallet::query()->sum('usd_balance'), 2);
        $outstandingNgn = round((float) UserWallet::query()->sum('ngn_balance'), 2);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'in_by_gateway' => $inByGateway,
            'total_in' => $totalIn,
            'wallet_credits' => $walletCredits,
            'reconciliation_gap' => round($totalIn - $walletCredits, 2),
            'wallet_by_type' => $walletByType,
            'paid_out' => $paidOut,
            'pending_out' => $pendingOut,
            'provider_cost' => round((float) ($orders->cost ?? 0), 2),
            'charged_to_user' => round((float) ($orders->charged ?? 0), 2),
            'gross_profit' => round((float) ($orders->profit ?? 0), 2),
            'outstanding_usd' => $outstandingUsd,
            'outstanding_ngn' => $outstandingNgn,
        ];
    }
}
