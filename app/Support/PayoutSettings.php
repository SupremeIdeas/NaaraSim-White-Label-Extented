<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-toggled settings for the payout engine (ROADMAP §Layer 0). The whole
 * money-OUT feature is OFF by default — nothing changes for existing users until
 * the owner switches it on. Kept tiny and Setting-backed like every other
 * NaaraSim feature flag.
 */
class PayoutSettings
{
    public const FLAG = 'payouts.enabled';

    public const MODE = 'payouts.mode';               // manual | autopilot

    public const MIN = 'payouts.min_withdrawal_usd';

    public const FREE_COUNT = 'payouts.free_payout_count'; // free payouts before KYC (BUILD-22 §3)

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    /** Settlement mode: manual approval (default) or scheduled autopilot. */
    public static function mode(): string
    {
        return Setting::getValue(self::MODE, 'manual') === 'autopilot' ? 'autopilot' : 'manual';
    }

    public static function autopilot(): bool
    {
        return self::mode() === 'autopilot';
    }

    /** Minimum a single withdrawal may request (USD). */
    public static function minWithdrawal(): float
    {
        return (float) Setting::getValue(self::MIN, 5.0);
    }

    /**
     * How many successful payouts a user may take before KYC-L2 is required
     * (BUILD-22 §3, Frank's request). Combined across every earner type. Default 5.
     */
    public static function freePayoutCount(): int
    {
        return max(0, (int) Setting::getValue(self::FREE_COUNT, 5));
    }
}
