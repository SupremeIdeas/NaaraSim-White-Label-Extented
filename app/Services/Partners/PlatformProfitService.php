<?php

namespace App\Services\Partners;

use App\Models\MerchantEarning;
use App\Models\OrderLog;
use App\Models\SmsOrder;
use Illuminate\Support\Carbon;

/**
 * Computes the PLATFORM-WIDE profit a partner shares in for a period.
 *
 * ── The exact boundary (auditable, non-overlapping) ──────────────────────────
 * Every order logs its profit as (charged − provider_cost):
 *   • OrderLog.profit  — eSIM orders (ProviderRouter)
 *   • SmsOrder.profit  — number orders (SmsNumberRouter)
 * For a MERCHANT's customer, `charged` is the MERCHANT price, so that profit row
 * ALSO contains the merchant's own margin — which is separately booked to the
 * merchant's ledger as a MerchantEarning ACCRUAL (merchant_price − retail).
 *
 * So a partner's shareable platform profit for a period is:
 *
 *     Σ OrderLog.profit + Σ SmsOrder.profit  −  Σ MerchantEarning.accrual
 *
 * Subtracting the merchant accruals removes exactly the merchant's cut, leaving
 * the platform's own retained margin (retail − cost) across every order — with
 * NO double counting of money that already belongs to a merchant. Partners
 * share a percentage of THIS figure only.
 *
 * Scope note: this covers the two ledgers that record per-transaction profit
 * (eSIM + numbers orders). Recurring Naara Line renewals and voice minutes are
 * not yet profit-logged per transaction and are intentionally excluded until
 * they are, so the number stays fully reconcilable to ledger rows.
 */
class PlatformProfitService
{
    /**
     * Net platform profit over [start, end] (inclusive of the boundaries), after
     * removing merchant cuts. May be negative in a heavily-refunded period; the
     * payout layer floors a partner's share at zero.
     */
    public function profitForPeriod(Carbon $start, Carbon $end): float
    {
        $esim = (float) OrderLog::whereBetween('created_at', [$start, $end])->sum('profit');
        $numbers = (float) SmsOrder::whereBetween('created_at', [$start, $end])->sum('profit');
        $merchantCut = (float) MerchantEarning::where('type', MerchantEarning::ACCRUAL)
            ->whereBetween('created_at', [$start, $end])->sum('amount');

        return round($esim + $numbers - $merchantCut, 4);
    }

    /** A per-source breakdown for the admin/audit view (cost stays internal). */
    public function breakdownForPeriod(Carbon $start, Carbon $end): array
    {
        $esim = (float) OrderLog::whereBetween('created_at', [$start, $end])->sum('profit');
        $numbers = (float) SmsOrder::whereBetween('created_at', [$start, $end])->sum('profit');
        $merchantCut = (float) MerchantEarning::where('type', MerchantEarning::ACCRUAL)
            ->whereBetween('created_at', [$start, $end])->sum('amount');

        return [
            'esim_profit' => round($esim, 4),
            'numbers_profit' => round($numbers, 4),
            'merchant_cut' => round($merchantCut, 4),
            'platform_profit' => round($esim + $numbers - $merchantCut, 4),
        ];
    }
}
