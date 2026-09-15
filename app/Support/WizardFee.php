<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * The Wizard convenience fee (roadmap §6). The first few wizard-completed
 * purchases are free; after that a small, ALWAYS-VISIBLE fee applies to
 * purchases finished through the Wizard — the dashboard/Numbers path stays
 * free. Amount + free allowance are admin-settable (Setting), defaulting to
 * $0.45 after 3 free sessions. This is a service fee, not a product price, so
 * it never flows through PricingEngine/MarginGuard.
 *
 * App Store payments-compliance doc (BUILD-5 §6): this is a genuinely-digital
 * in-app fee, which on iOS is either StoreKit IAP or hidden — no native IAP
 * integration exists here, so on iOS it's hidden/disabled (option b) rather
 * than ever collected through our own gateway inside the iOS app. Web and
 * Android are completely unaffected.
 */
class WizardFee
{
    /** The configured fee (USD). Zero disables the fee entirely. */
    public static function amount(): float
    {
        return round((float) Setting::getValue('wizard.fee_usd', 0.45), 2);
    }

    /** How many wizard purchases are free before the fee kicks in. */
    public static function freeSessions(): int
    {
        return max(0, (int) Setting::getValue('wizard.free_sessions', 3));
    }

    /** Does the fee apply to this user's NEXT wizard purchase? */
    public static function appliesTo(User $user): bool
    {
        if (AppPlatform::isIosBuild()) {
            return false;
        }

        return self::amount() > 0 && (int) $user->wizard_uses >= self::freeSessions();
    }

    /** The fee to charge this user right now (0 while within the free allowance). */
    public static function forUser(User $user): float
    {
        return self::appliesTo($user) ? self::amount() : 0.0;
    }

    /** Free purchases remaining before the fee starts (for friendly copy). */
    public static function freeRemaining(User $user): int
    {
        return max(0, self::freeSessions() - (int) $user->wizard_uses);
    }
}
