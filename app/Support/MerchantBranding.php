<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\User;

/**
 * Co-branding + invite plumbing (ROADMAP §Layer 3.3). A merchant shares
 * /merchant/{slug}/join; the visitor's chosen merchant is held in the session and
 * permanently stamped onto their account (users.merchant_id) when they register.
 * From then on the customer sees the merchant's logo + "Powered by NaaraSim".
 *
 * The merchant NEVER replaces NaaraSim branding — it sits alongside it, always
 * with the "Powered by NaaraSim" line (NaaraSim is the heart of the service; the
 * merchant is an extension).
 */
class MerchantBranding
{
    public const SESSION_KEY = 'merchant_invite';

    /** Store an invite in the session if the slug is a real, ACTIVE merchant. */
    public static function captureInvite(string $slug): ?Merchant
    {
        $merchant = Merchant::query()->where('slug', $slug)->where('status', Merchant::ACTIVE)->first();
        if ($merchant !== null) {
            session([self::SESSION_KEY => $merchant->slug]);
        }

        return $merchant;
    }

    /** The active merchant behind the current invite session, if any. */
    public static function inviteMerchant(): ?Merchant
    {
        $slug = session(self::SESSION_KEY);
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Merchant::query()->where('slug', $slug)->where('status', Merchant::ACTIVE)->first();
    }

    /**
     * On registration, permanently link the new user to the invite's merchant and
     * clear the session flag. A customer belongs to exactly one merchant, set once.
     */
    public static function consumeInviteFor(User $user): void
    {
        $merchant = self::inviteMerchant();
        if ($merchant !== null && $user->merchant_id === null) {
            // Referral-margin lock-in (BUILD-4 §3.3): snapshot the merchant's
            // reseller margin AT SIGNUP. This user then pays wholesale + this
            // exact margin for the life of the account, regardless of later margin
            // changes — unless an admin deliberately overrides merchant_margin_pct.
            $lockedMargin = $merchant->reseller_margin_pct !== null
                ? (float) $merchant->reseller_margin_pct
                : \App\Support\MerchantSettings::resellerMarginPct();

            $user->forceFill([
                'merchant_id' => $merchant->id,
                'merchant_margin_pct' => $lockedMargin,
            ])->save();
        }
        session()->forget(self::SESSION_KEY);
    }

    /** The active merchant a user is a CUSTOMER of (drives co-branding), or null. */
    public static function forCustomer(?User $user): ?Merchant
    {
        if ($user === null || $user->merchant_id === null) {
            return null;
        }
        $merchant = $user->merchant;

        return $merchant && $merchant->isActive() ? $merchant : null;
    }
}
