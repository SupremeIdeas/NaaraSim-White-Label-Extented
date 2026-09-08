<?php

namespace App\Support;

use App\Models\EsimPlan;
use App\Models\Setting;

/**
 * Public pricing display (Module 29). Decides what the public /pricing page
 * shows and how:
 *
 *   - mode "auto"  (default): real plans when an eSIM provider is live AND the
 *     catalogue has active plans; otherwise the honest estimate tiers.
 *   - mode "live":  always try to show real plans (falls back to estimate only
 *     if there is genuinely nothing to show).
 *   - mode "estimate": always show the estimate tiers, even if live plans exist
 *     (useful before launch, or to advertise "from" pricing).
 *
 * Money-safety: real plans are surfaced through `display_price` only — cost is
 * never read here. Estimate tiers are admin-authored "from" figures, clearly
 * labelled as estimates in the view.
 */
class PricingDisplay
{
    /** Resolved public mode: 'live' or 'estimate'. */
    public static function mode(): string
    {
        $setting = Setting::getValue('pricing.public_mode', 'auto');

        if ($setting === 'estimate') {
            return 'estimate';
        }
        if ($setting === 'live') {
            return self::hasLivePlans() ? 'live' : 'estimate';
        }

        // auto
        return (self::esimLive() && self::hasLivePlans()) ? 'live' : 'estimate';
    }

    public static function isLive(): bool
    {
        return self::mode() === 'live';
    }

    /** Any eSIM provider actually configured/active. */
    public static function esimLive(): bool
    {
        return ProviderStatus::isActive('esimgo')
            || ProviderStatus::isActive('airalo')
            || ProviderStatus::isActive('quibity');
    }

    public static function hasLivePlans(): bool
    {
        return EsimPlan::where('is_active', true)->exists();
    }

    /**
     * Active plans for the public comparison, featured first. Returns models
     * (the view reads display_price + public fields only, never cost).
     *
     * @return \Illuminate\Support\Collection<int, EsimPlan>
     */
    public static function livePlans(int $limit = 6)
    {
        return EsimPlan::where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('final_retail_usd')
            ->limit($limit)
            ->get();
    }

    /**
     * Admin-authored estimate tiers, or sensible defaults. Each tier is a "from"
     * figure (USD) with a label + blurb — an estimate, never a live quote.
     *
     * @return list<array{name:string,from_usd:float,data:string,validity:string,blurb:string}>
     */
    public static function estimateTiers(): array
    {
        $tiers = Setting::getValue('pricing.estimate_tiers', null);

        return is_array($tiers) && $tiers !== [] ? $tiers : self::defaultTiers();
    }

    /** @return list<array{name:string,from_usd:float,data:string,validity:string,blurb:string}> */
    public static function defaultTiers(): array
    {
        return [
            ['name' => 'Traveller', 'from_usd' => 5.0, 'data' => '1 GB', 'validity' => '7 days',
             'blurb' => 'A short city break or a long weekend — maps, chat and email without roaming bills.'],
            ['name' => 'Explorer', 'from_usd' => 12.0, 'data' => '3 GB', 'validity' => '30 days',
             'blurb' => 'The all-rounder for a two–three week trip with social, browsing and the odd video call.'],
            ['name' => 'Global', 'from_usd' => 26.0, 'data' => '10 GB', 'validity' => '30 days',
             'blurb' => 'Heavy use, hotspotting or a multi-country journey where you live off your data.'],
        ];
    }
}
