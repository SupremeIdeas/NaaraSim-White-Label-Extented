<?php

namespace App\Services\Merchants;

use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Services\Kyc\KycService;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Pricing\CurrencyService;
use App\Support\MerchantSettings;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Merchant cash-out (ROADMAP §Layer 3.4). Turns a merchant's accrued reseller
 * earnings into a real bank transfer through the SAME payout engine used for
 * customer withdrawals. The earnings are HELD the instant the request is created
 * and, if the transfer fails, the PayoutReversed event returns exactly that hold
 * (ReturnMerchantEarnings). FX is locked at request time.
 */
class MerchantWithdrawalService
{
    public function __construct(
        private MerchantEarningsService $earnings,
        private PayoutService $payouts,
        private CurrencyService $currency,
        private KycService $kyc,
        private PayoutThreshold $threshold,
    ) {}

    public function availableUsd(Merchant $merchant): float
    {
        return round($this->earnings->balance($merchant), 2);
    }

    /** Convert a USD amount to the destination account's currency (locked now). */
    private function localAmount(float $usd, string $currency): float
    {
        return match (strtoupper($currency)) {
            'USD' => round($usd, 2),
            'NGN' => round($usd * $this->currency->getUsdToNgn(), 2),
            default => throw new PayoutException("Withdrawals to {$currency} aren't available yet."),
        };
    }

    /**
     * Request a cash-out of `$usd` earnings to the merchant owner's verified
     * account. Holds the earnings, then creates a payout_request on the
     * merchant_earnings bucket.
     *
     * @throws PayoutException|MerchantException
     */
    public function request(Merchant $merchant, PayoutAccount $account, float $usd): PayoutRequest
    {
        if (! PayoutSettings::enabled()) {
            throw new PayoutException('Withdrawals are not available right now.');
        }
        if (! $merchant->isActive()) {
            throw new PayoutException('Your merchant account is not active.');
        }

        $owner = $merchant->owner;
        // "Verified" here means the payout PROVIDER confirmed the account name
        // before it was saved (PayoutAccountService) — adding one is free of
        // KYC (BUILD-4 §1 deferred-verification design); the unified
        // free-payout threshold below is what actually gates KYC-L2.
        if ($account->user_id !== $owner->id || ! $account->is_verified) {
            throw new PayoutException('Choose a verified payout account.');
        }

        $usd = round($usd, 2);
        if ($usd <= 0 || $usd > $this->availableUsd($merchant)) {
            throw new PayoutException('Amount exceeds your available earnings.');
        }
        if ($usd < PayoutSettings::minWithdrawal()) {
            throw new PayoutException('Below the minimum withdrawal of $'.number_format(PayoutSettings::minWithdrawal(), 2).'.');
        }
        // Unified free-payout threshold (BUILD-22 §3): the first N payouts across
        // all earner types are KYC-free; past that, KYC-L2 is required.
        if (! $this->threshold->canWithdraw($owner)) {
            throw new PayoutException(
                "You've reached your free payout limit — verify your identity to keep earning and withdrawing."
            );
        }
        // Larger single payouts can require full business KYB (L3) — an admin
        // rule that ships dormant (§1.3) and is enforced only once switched on.
        if (MerchantSettings::kybOverThresholdEnabled()
            && $usd > MerchantSettings::kybThresholdUsd()
            && ! $this->kyc->hasLevel($owner, KycVerification::L3)) {
            throw new PayoutException(
                'Business (KYB) verification is required to cash out more than $'
                .number_format(MerchantSettings::kybThresholdUsd(), 2).' in one payout. '
                .'Verify your business to continue, or withdraw a smaller amount.'
            );
        }

        $currency = strtoupper($account->currency);
        $localAmount = $this->localAmount($usd, $currency);
        $reference = 'mwd:'.Str::uuid();

        return DB::transaction(function () use ($merchant, $owner, $usd, $account, $localAmount, $currency, $reference) {
            // HOLD the earnings before any money is promised (idempotent).
            $this->earnings->hold($merchant, $usd, 'earn-hold:'.$reference, 'Cash withdrawal');

            $request = $this->payouts->createRequest($owner, $localAmount, $currency, 'merchant_earnings', $account, $reference);
            // Record the USD earnings held so the reversal returns the exact bucket.
            $request->forceFill(['credit_amount' => $usd])->save();

            return $request;
        });
    }
}
