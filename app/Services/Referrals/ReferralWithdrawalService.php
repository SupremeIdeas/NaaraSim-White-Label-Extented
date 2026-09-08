<?php

namespace App\Services\Referrals;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Pricing\CurrencyService;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referral-earnings cash-out (NAARA-BUILD-22 §2/§4). Turns a referrer's accrued
 * margin-share earnings into a real transfer through the SAME payout engine as
 * merchant/partner cash-outs. Earnings are HELD the instant the request is
 * created; a failed transfer returns exactly that hold (ReturnReferralEarnings).
 *
 * KYC is gated by the unified free-payout threshold (§3), NOT a blanket KYC wall:
 * the first N payouts need no verification, then KYC-L2 is required.
 */
class ReferralWithdrawalService
{
    public function __construct(
        private ReferralEarningsService $earnings,
        private PayoutService $payouts,
        private CurrencyService $currency,
        private PayoutThreshold $threshold,
    ) {}

    public function availableUsd(User $user): float
    {
        return round($this->earnings->balance($user), 2);
    }

    private function localAmount(float $usd, string $currency): float
    {
        return match (strtoupper($currency)) {
            'USD' => round($usd, 2),
            'NGN' => round($usd * $this->currency->getUsdToNgn(), 2),
            default => throw new PayoutException("Withdrawals to {$currency} aren't available yet."),
        };
    }

    /**
     * Request a cash-out of `$usd` referral earnings to the user's verified
     * account. Holds the earnings, then creates a payout_request on the
     * referral_earnings bucket. Sends immediately only when $autoSend is set
     * (the automatic run); otherwise it follows the engine's manual/autopilot mode.
     *
     * @throws PayoutException
     */
    public function request(User $user, PayoutAccount $account, float $usd, bool $autoSend = false): PayoutRequest
    {
        if (! PayoutSettings::enabled()) {
            throw new PayoutException('Withdrawals are not available right now.');
        }
        if ($account->user_id !== $user->id || ! $account->is_verified) {
            throw new PayoutException('Choose a verified payout account.');
        }

        $usd = round($usd, 2);
        if ($usd <= 0 || $usd > $this->availableUsd($user)) {
            throw new PayoutException('Amount exceeds your available earnings.');
        }
        if ($usd < PayoutSettings::minWithdrawal()) {
            throw new PayoutException('Below the minimum withdrawal of $'.number_format(PayoutSettings::minWithdrawal(), 2).'.');
        }
        // Unified free-payout threshold (§3): first N payouts are KYC-free.
        if (! $this->threshold->canWithdraw($user)) {
            throw new PayoutException(
                "You've reached your free payout limit — verify your identity to keep earning and withdrawing."
            );
        }

        $currency = strtoupper($account->currency);
        $local = $this->localAmount($usd, $currency);
        $reference = 'rwd:'.Str::uuid();

        return DB::transaction(function () use ($user, $usd, $account, $local, $currency, $reference, $autoSend) {
            // HOLD the earnings before any money is promised (idempotent).
            $this->earnings->hold($user, $usd, 'earn-hold:'.$reference, 'Referral cash withdrawal');

            $request = $this->payouts->createRequest($user, $local, $currency, 'referral_earnings', $account, $reference);
            $request->forceFill(['payee_type' => 'referral', 'credit_amount' => $usd])->save();

            if ($autoSend || PayoutSettings::autopilot()) {
                $this->payouts->send($request);
            }

            return $request;
        });
    }
}
