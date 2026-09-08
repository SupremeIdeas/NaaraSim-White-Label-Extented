<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-toggled merchant-programme settings (ROADMAP §Layer 3). Off by default;
 * the reseller margin is admin-owned (a merchant never prices their own goods)
 * and MarginGuard still floors every resulting price.
 */
class MerchantSettings
{
    public const FLAG = 'merchants.enabled';

    public const MARGIN = 'merchants.reseller_margin_pct';

    // Eligibility to migrate to a merchant — meet ANY one (ROADMAP §Layer 3).
    public const MIN_SPEND = 'merchants.min_spend_usd';        // lifetime spend

    public const ENROLLMENT_FEE = 'merchants.enrollment_fee_usd'; // one-time fast route

    public const MIN_REFERRALS = 'merchants.min_referrals';    // referred users

    /** One-time price to self-upgrade to Merchant V2 (client management). */
    public const UPGRADE_PRICE = 'merchants.upgrade_price_usd';

    /** One-time flat bonus paid to a merchant when someone they invited becomes
     *  a merchant too (BUILD-7 §4). Default 0 = off. Deliberately a single flat
     *  bonus, NOT a recurring percentage — one hop only, never a downline. */
    public const MERCHANT_REFERRAL_BONUS = 'merchants.merchant_referral_bonus_usd';

    // Deferred-verification payout threshold (BUILD-4 §1.3). KYB is no longer a
    // front-of-funnel gate; instead a merchant verifies at payout time. Payouts
    // at/under KYB_THRESHOLD clear at KYC-L2 (a verified bank account); larger
    // ones require business KYB (L3) — but ONLY when KYB_OVER_THRESHOLD is on, so
    // the rule can be switched on once the compliance stance is finalised.
    public const KYB_THRESHOLD = 'merchants.kyb_threshold_usd';

    public const KYB_OVER_THRESHOLD = 'merchants.kyb_over_threshold_enabled';

    /** Auto-promote eligible users to Merchant V1 (BUILD-4 §4.3). Default: OFF —
     *  the recommended flow is the admin "Ready to promote" queue, one click each. */
    public const AUTO_PROMOTE = 'merchants.auto_promote_enabled';

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    /** Global reseller margin % applied over retail (per-merchant can override). */
    public static function resellerMarginPct(): float
    {
        return (float) Setting::getValue(self::MARGIN, 10.0);
    }

    public static function minSpendUsd(): float
    {
        return (float) Setting::getValue(self::MIN_SPEND, 75.0);
    }

    public static function enrollmentFeeUsd(): float
    {
        return (float) Setting::getValue(self::ENROLLMENT_FEE, 50.0);
    }

    public static function minReferrals(): int
    {
        return (int) Setting::getValue(self::MIN_REFERRALS, 1000);
    }

    /** Admin-editable V2 upgrade price (default $125) — never hardcoded. */
    public static function upgradePriceUsd(): float
    {
        return (float) Setting::getValue(self::UPGRADE_PRICE, 125.0);
    }

    /** One-time flat bonus (USD) for a merchant-to-merchant referral. 0 = off. */
    public static function merchantReferralBonusUsd(): float
    {
        return max(0.0, (float) Setting::getValue(self::MERCHANT_REFERRAL_BONUS, 0.0));
    }

    /** Single-payout amount above which business KYB is required (default $500). */
    public static function kybThresholdUsd(): float
    {
        return (float) Setting::getValue(self::KYB_THRESHOLD, 500.0);
    }

    /** Whether the "KYB required above the threshold" payout rule is enforced.
     *  Off by default — the mechanism ships ready but dormant until an admin
     *  turns it on (owner decision). */
    public static function kybOverThresholdEnabled(): bool
    {
        return (bool) Setting::getValue(self::KYB_OVER_THRESHOLD, false);
    }

    /** Whether eligible users are auto-promoted to Merchant V1 (off by default). */
    public static function autoPromoteEnabled(): bool
    {
        return (bool) Setting::getValue(self::AUTO_PROMOTE, false);
    }
}
