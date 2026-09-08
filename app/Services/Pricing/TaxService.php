<?php

namespace App\Services\Pricing;

use App\Support\TaxRates;

/**
 * Tax/VAT support layer (BUILD-7 §2). A thin, single-responsibility service
 * alongside PricingEngine/CurrencyService. Tax is ADDITIVE to what the user pays
 * and is kept fully separate from the cost+profit / margin-guard math — it never
 * touches PricingEngine's numbers. Returns 0 unless an admin has configured a
 * rate for the sale's country, so nothing is ever silently charged.
 */
class TaxService
{
    /** The tax amount (in the same units as $retail) for a country, or 0. */
    public function taxFor(?string $country, float $retail): float
    {
        $rate = TaxRates::rateFor($country);
        if ($rate <= 0 || $retail <= 0) {
            return 0.0;
        }

        return round($retail * $rate / 100, 2);
    }

    /** The configured rate percent for a country (0 when none). */
    public function rateFor(?string $country): float
    {
        return TaxRates::rateFor($country);
    }

    /** True only where the operator has explicitly enabled a rate. */
    public function applies(?string $country): bool
    {
        return TaxRates::rateFor($country) > 0;
    }
}
