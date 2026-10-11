<?php

namespace App\Services\Staff;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\Pricing\CurrencyService;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff compensation cash-out (NAARA-BUILD-23 §4). Same shared payout engine as
 * every other earner — holds the staff_earnings bucket, creates a payout_request
 * on the staff_earnings bucket, and a failed transfer returns the exact hold
 * (ReturnStaffEarnings).
 *
 * Staff are EXEMPT from the free-payout/KYC threshold (Frank's decision): they
 * are already vetted at account-creation, a different trust relationship than an
 * anonymous referral earner — so no PayoutThreshold gate here.
 */
class StaffWithdrawalService
{
    public function __construct(
        private StaffEarningsService $earnings,
        private PayoutService $payouts,
        private CurrencyService $currency,
    ) {}

    public function availableUsd(User $staff): float
    {
        return round($this->earnings->balance($staff), 2);
    }

    /** @throws PayoutException */
    public function request(User $staff, PayoutAccount $account, float $usd, bool $autoSend = false): PayoutRequest
    {
        if (! PayoutSettings::enabled()) {
            throw new PayoutException('Withdrawals are not available right now.');
        }
        if ($account->user_id !== $staff->id || ! $account->is_verified) {
            throw new PayoutException('Choose a verified payout account.');
        }

        $usd = round($usd, 2);
        if ($usd <= 0 || $usd > $this->availableUsd($staff)) {
            throw new PayoutException('Amount exceeds your available earnings.');
        }
        if ($usd < PayoutSettings::minWithdrawal()) {
            throw new PayoutException('Below the minimum withdrawal of $'.number_format(PayoutSettings::minWithdrawal(), 2).'.');
        }
        // NB: no free-payout/KYC threshold — staff are exempt by policy.

        $currency = strtoupper($account->currency);
        $quote = app(\App\Services\Payouts\PayoutQuoter::class)->quote($usd, $currency, $account);
        $local = $quote['local_amount'];
        $reference = 'swd:'.Str::uuid();

        return DB::transaction(function () use ($staff, $usd, $account, $quote, $local, $currency, $reference, $autoSend) {
            $this->earnings->hold($staff, $usd, 'earn-hold:'.$reference, 'Staff compensation withdrawal');

            $request = $this->payouts->createRequest($staff, $local, $currency, 'staff_earnings', $account, $reference, $quote['quote']);
            $request->forceFill(['payee_type' => 'staff', 'credit_amount' => $usd])->save();

            // No provider call here — ever. createRequest() queued the Payout Guardian
            // (afterCommit); it approves only when auto mode is on and every gate passes,
            // and the send itself runs in SendPayoutJob on the `payouts` queue.

            return $request;
        });
    }
}
