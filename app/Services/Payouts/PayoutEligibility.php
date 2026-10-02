<?php

namespace App\Services\Payouts;

use App\Models\KycVerification;
use App\Models\PayoutAccount;
use App\Models\PayoutRailEnrollment;
use App\Models\User;
use App\Support\PayoutSettings;

/**
 * THE predicates that decide whether an earner's balance is paid by the automatic
 * runs (payouts:earnings-run / partners:payout-run). One definition, two consumers:
 * the runs themselves and the Funding Radar's "Due at next sweep" — if either changes,
 * both change. Extracted without behaviour change.
 */
class PayoutEligibility
{
    public function __construct(private PayoutThreshold $threshold) {}

    /** The destination the automatic runs pay: the newest verified account. */
    public function verifiedAccount(User|int $user): ?PayoutAccount
    {
        return PayoutAccount::where('user_id', $user instanceof User ? $user->id : $user)
            ->where('is_verified', true)->latest('id')->first();
    }

    /** At or above the minimum a single withdrawal may request. */
    public function meetsMinimum(float $usd): bool
    {
        return $usd >= PayoutSettings::minWithdrawal();
    }

    /** Free allowance left, or identity-verified (staff are exempt by policy and never asked). */
    public function kycAllows(User $user): bool
    {
        return $this->threshold->canWithdraw($user);
    }

    /**
     * Will the next automatic run pay this earner? (Radar view: adds the radar-only
     * conditions — a paused enrollment or denied country are never swept.)
     *
     * @return array{eligible: bool, reason: ?string}
     */
    public function nextSweep(User $user, float $balanceUsd, ?PayoutRailEnrollment $enrollment = null): array
    {
        return match (true) {
            ! PayoutSettings::enabled() => ['eligible' => false, 'reason' => 'payouts_disabled'],
            ! PayoutSettings::autopilot() => ['eligible' => false, 'reason' => 'manual_mode'],
            $enrollment !== null && $enrollment->status !== PayoutRailEnrollment::ACTIVE => ['eligible' => false, 'reason' => 'enrollment_'.$enrollment->status],
            ! $this->meetsMinimum($balanceUsd) => ['eligible' => false, 'reason' => 'below_minimum'],
            $this->verifiedAccount($user) === null => ['eligible' => false, 'reason' => 'no_verified_account'],
            in_array(strtoupper((string) $user->country_code), PayoutSettings::deniedCountries(), true) => ['eligible' => false, 'reason' => 'country_denied'],
            ! $this->kycAllows($user) => ['eligible' => false, 'reason' => 'kyc_required'],
            default => ['eligible' => true, 'reason' => null],
        };
    }
}
