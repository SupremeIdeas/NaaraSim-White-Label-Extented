<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Pricing\CurrencyService;
use App\Support\CreditSettings;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NaaraCredit → cash (ROADMAP §Layer 1). Turns a user's WITHDRAWABLE credits
 * (first-referral rewards only) into a real bank transfer.
 *
 * KYC is gated by the unified free-payout threshold (NAARA-BUILD-22 §3, same as
 * ReferralWithdrawalService/MerchantWithdrawalService), NOT a blanket KYC wall:
 * setting up a payout account and the first N withdrawals need no verification,
 * then KYC-L2 is required.
 *
 * Money-safety: the withdrawn USD was already granted as a referral bonus the
 * platform budgeted for — it never touches admin margin. The credits are HELD
 * (spent from the withdrawable bucket) the instant the request is created, the
 * exact held amount is recorded on the payout, and if the transfer fails the
 * engine's PayoutReversed event returns precisely those credits. FX is locked at
 * request time; withdrawals are capped at the withdrawable bucket.
 */
class WithdrawalService
{
    public function __construct(
        private CreditService $credits,
        private PayoutService $payouts,
        private PayoutThreshold $threshold,
        private CurrencyService $currency,
    ) {}

    /** Withdrawable balance expressed in USD at the current rate. */
    public function availableUsd(User $user): float
    {
        return CreditSettings::creditsToUsd($this->credits->withdrawableBalance($user));
    }

    /** Convert a USD amount to the destination account's currency (locked now). */
    public function localAmount(float $usd, string $currency): float
    {
        return match (strtoupper($currency)) {
            'USD' => round($usd, 2),
            'NGN' => round($usd * $this->currency->getUsdToNgn(), 2),
            default => throw new PayoutException("Withdrawals to {$currency} aren't available yet."),
        };
    }

    /**
     * Request a cash-out of `$credits` withdrawable credits to a verified account.
     * Holds the credits and creates a payout_request through the engine (which
     * then follows manual-approval / autopilot settlement).
     *
     * @throws PayoutException
     */
    public function request(User $user, float $credits, PayoutAccount $account): PayoutRequest
    {
        if (! PayoutSettings::enabled()) {
            throw new PayoutException('Withdrawals are not available right now.');
        }
        // Unified free-payout threshold (§3): first N payouts are KYC-free.
        if (! $this->threshold->canWithdraw($user)) {
            throw new PayoutException(
                "You've reached your free payout limit — verify your identity to keep withdrawing."
            );
        }
        if ($account->user_id !== $user->id || ! $account->is_verified) {
            throw new PayoutException('Choose a verified payout account.');
        }

        $credits = round($credits, 2);
        if ($credits <= 0 || $credits > $this->credits->withdrawableBalance($user)) {
            throw new PayoutException('Amount exceeds your withdrawable balance.');
        }

        $usd = CreditSettings::creditsToUsd($credits);
        if ($usd < PayoutSettings::minWithdrawal()) {
            throw new PayoutException('Below the minimum withdrawal of $'.number_format(PayoutSettings::minWithdrawal(), 2).'.');
        }

        $currency = strtoupper($account->currency);
        $localAmount = $this->localAmount($usd, $currency);
        $reference = 'wd:'.Str::uuid();

        return DB::transaction(function () use ($user, $credits, $account, $localAmount, $currency, $reference) {
            // HOLD the credits (spend from the withdrawable bucket) before any
            // money is promised. Idempotent on the hold reference.
            $this->credits->spendWithdrawable($user, $credits, 'withdraw', 'wd-hold:'.$reference, 'Cash withdrawal');

            $request = $this->payouts->createRequest($user, $localAmount, $currency, 'referral_credits', $account, $reference);
            $request->forceFill(['credit_amount' => $credits])->save();

            return $request;
        });
    }
}
