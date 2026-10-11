<?php

namespace App\Services\Payouts\Rail;

use App\Support\PayoutSettings;

/**
 * Where Stripe can actually pay (confirmed on Stripe's docs, 2026-10-02 — docs/payouts/PROVIDER-RESEARCH.md):
 *  - Connect cross-border payouts only reach connected accounts in the US, UK, EEA, Canada and Switzerland.
 *  - Anywhere else (e.g. Nigeria, Ghana, Kenya, South Africa) needs Global Payouts, which is available to US/UK
 *    businesses with Treasury. That is an owner decision, recorded in the setting `payouts.stripe_global_payouts`.
 * Until then a Stripe corridor outside the list is treated as unavailable even if someone enabled the row.
 */
class StripeRegions
{
    /** ISO-2: US, GB, CA, CH, the EU-27, plus Iceland, Liechtenstein, Norway (EEA). */
    public const CROSS_BORDER = [
        'US', 'GB', 'CA', 'CH',
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'IS', 'LI', 'NO',
    ];

    public static function allows(string $country): bool
    {
        return PayoutSettings::stripeGlobalPayouts() || in_array(strtoupper($country), self::CROSS_BORDER, true);
    }
}
