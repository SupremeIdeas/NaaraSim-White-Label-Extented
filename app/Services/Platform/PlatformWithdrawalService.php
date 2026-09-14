<?php

namespace App\Services\Platform;

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
 * Prompt 21-EXT §5.3 — the platform-earnings cash-out. Turns the global
 * white-label sale proceeds into a real bank transfer through the SAME
 * payout engine merchants already use (PayoutService/PayoutThreshold), never
 * a parallel mechanism. Unlike MerchantWithdrawalService there is no owning
 * merchant: any `super_admin` may request a cash-out of the ONE global
 * balance to THEIR OWN verified payout account — "admin can withdraw this
 * the way normal users withdraw funds" (owner's own framing).
 */
class PlatformWithdrawalService
{
    public function __construct(
        private PlatformEarningsService $earnings,
        private PayoutService $payouts,
        private CurrencyService $currency,
        private PayoutThreshold $threshold,
    ) {}

    public function availableUsd(): float
    {
        return round($this->earnings->balance(), 2);
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
     * Request a cash-out of `$usd` platform earnings to the acting admin's
     * own verified payout account. Holds the earnings, then creates a
     * payout_request on the platform_earnings bucket.
     *
     * @throws PayoutException
     */
    public function request(User $admin, PayoutAccount $account, float $usd): PayoutRequest
    {
        if (! $admin->hasRole('super_admin')) {
            throw new PayoutException('Only a super admin may withdraw platform earnings.');
        }
        if (! PayoutSettings::enabled()) {
            throw new PayoutException('Withdrawals are not available right now.');
        }
        if ($account->user_id !== $admin->id || ! $account->is_verified) {
            throw new PayoutException('Choose a verified payout account.');
        }

        $usd = round($usd, 2);
        if ($usd <= 0 || $usd > $this->availableUsd()) {
            throw new PayoutException('Amount exceeds the platform\'s available earnings.');
        }
        if ($usd < PayoutSettings::minWithdrawal()) {
            throw new PayoutException('Below the minimum withdrawal of $'.number_format(PayoutSettings::minWithdrawal(), 2).'.');
        }
        if (! $this->threshold->canWithdraw($admin)) {
            throw new PayoutException("You've reached your free payout limit — verify your identity to keep withdrawing.");
        }

        $currency = strtoupper($account->currency);
        $localAmount = $this->localAmount($usd, $currency);
        $reference = 'pwd:'.Str::uuid();

        return DB::transaction(function () use ($admin, $usd, $account, $localAmount, $currency, $reference) {
            // HOLD the earnings before any money is promised (idempotent).
            $this->earnings->hold($usd, 'plat-hold:'.$reference, 'Platform earnings withdrawal');

            $request = $this->payouts->createRequest($admin, $localAmount, $currency, 'platform_earnings', $account, $reference);
            // Record the USD earnings held so a reversal returns the exact amount.
            $request->forceFill(['credit_amount' => $usd])->save();

            return $request;
        });
    }
}
