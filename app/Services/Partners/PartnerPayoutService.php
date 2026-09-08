<?php

namespace App\Services\Partners;

use App\Models\Partner;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\Pricing\CurrencyService;
use App\Support\PartnerSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a partner's platform profit-share into real money each period, on the
 * SAME payout engine as merchant/customer cash-outs (never a parallel system).
 *
 * Per run, for an active partner:
 *   1. Accrue every fully-completed cadence period since the last one paid —
 *      (platform profit for that period) × (their profit-share %) — to the
 *      partner's ledger. Idempotent per period, so a re-run never double-pays.
 *   2. Pay out the available balance to the owner's verified payout account:
 *      manual mode leaves a PENDING request for admin approval; auto mode sends
 *      it straight through PayoutService.
 *
 * Withdrawal is UNCONDITIONAL — no referral count, no minimum spend, no
 * enrollment fee, no minimum-withdrawal floor. The only practical requirement is
 * a verified payout account for the transfer to land; until then the balance
 * simply accrues and is paid on the next run. This is deliberately kept separate
 * from MerchantWithdrawalService's gated logic.
 */
class PartnerPayoutService
{
    public function __construct(
        private PlatformProfitService $profit,
        private PartnerEarningsService $earnings,
        private PayoutService $payouts,
        private CurrencyService $currency,
    ) {
    }

    /** Run one partner: accrue due periods, then pay the balance out. */
    public function runPartner(Partner $partner): array
    {
        if (! PartnerSettings::enabled() || ! $partner->isActive()) {
            return ['periods' => 0, 'accrued' => 0.0, 'payout' => null];
        }

        $periods = 0;
        $accrued = 0.0;
        // Catch up every completed period since the last one paid (capped for safety).
        while ($periods < 60 && ($window = $this->nextDuePeriod($partner)) !== null) {
            $share = round($this->profit->profitForPeriod($window['start'], $window['end']) * ((float) $partner->profit_share_pct / 100), 4);
            $ref = 'pshare:'.$partner->id.':'.$window['end']->toDateString();
            if ($share > 0) {
                $this->earnings->accrue($partner, $share, $ref, $window['start'], $window['end']);
                $accrued += $share;
            }
            $partner->forceFill(['last_period_end' => $window['end']->toDateString()])->save();
            $partner->refresh();
            $periods++;
        }

        $payout = $this->attemptPayout($partner);

        return ['periods' => $periods, 'accrued' => round($accrued, 4), 'payout' => $payout?->id];
    }

    /**
     * The next fully-completed cadence period not yet paid, or null if none is
     * due. The first period starts at the partner's join date (so we never pay
     * for profit earned before they were a partner); later periods align to the
     * cadence boundary.
     */
    public function nextDuePeriod(Partner $partner): ?array
    {
        $from = $partner->last_period_end
            ? $partner->last_period_end->copy()->addDay()->startOfDay()
            : $partner->created_at->copy()->startOfDay();

        $end = $partner->payout_cadence === Partner::CADENCE_WEEKLY
            ? $from->copy()->endOfWeek()
            : $from->copy()->endOfMonth();

        // Only a period that has fully ended can be paid.
        return $end->lt(now()) ? ['start' => $from, 'end' => $end] : null;
    }

    /** Pay the full available balance to a verified account (unconditional). */
    private function attemptPayout(Partner $partner): ?PayoutRequest
    {
        $balance = round($this->earnings->balance($partner), 2);
        if ($balance <= 0) {
            return null;
        }

        $account = PayoutAccount::where('user_id', $partner->owner_user_id)
            ->where('is_verified', true)->latest('id')->first();
        if ($account === null) {
            return null; // owed — paid on the next run once an account is verified
        }

        $currency = strtoupper($account->currency);
        $local = $this->localAmount($balance, $currency);
        $reference = 'ppo:'.Str::uuid();

        return DB::transaction(function () use ($partner, $balance, $account, $local, $currency, $reference) {
            // Hold the earnings before any money is promised (idempotent).
            $this->earnings->hold($partner, $balance, 'earn-hold:'.$reference, 'Partner payout');

            $request = $this->payouts->createRequest($partner->owner, $local, $currency, 'partner_earnings', $account, $reference);
            $request->forceFill(['credit_amount' => $balance])->save();

            // Auto mode transfers immediately; manual mode waits for admin approval.
            if ($partner->payout_mode === Partner::MODE_AUTO) {
                $this->payouts->send($request);
            }

            return $request->refresh();
        });
    }

    private function localAmount(float $usd, string $currency): float
    {
        return match (strtoupper($currency)) {
            'USD' => round($usd, 2),
            'NGN' => round($usd * $this->currency->getUsdToNgn(), 2),
            default => throw new PayoutException("Payouts to {$currency} aren't available yet."),
        };
    }
}
