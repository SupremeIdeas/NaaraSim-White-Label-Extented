<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Owner request (2026-09-15) — the optional "custom theme request" add-on
 * offered at Merchant V2 white-label license purchase time. A merchant can
 * skip it entirely (None — normal setup, billed with no extra cost) or pick
 * a tier; whichever they pick is billed together with the license in ONE
 * wallet charge (WhiteLabelLicenseService::payAndActivate()).
 *
 * A fixed set of four named tiers (not an open admin-CRUD catalog like
 * WhiteLabelLicensePlan) — the owner specified exactly these four with
 * exact prices. Setting-backed so a price can still be retuned without a
 * deploy, same as every other admin-tunable price in this codebase; the
 * DEFAULT_PRICES below are the owner's own figures and are never read
 * directly at a charging point — always through priceFor().
 */
class ThemeAddonCatalog
{
    public const NONE = 'none';

    public const BASIC = 'basic';

    public const ELEGANT = 'elegant';

    public const PREMIUM = 'premium';

    /** @var array<string, string> */
    public const LABELS = [
        self::NONE => 'No custom theme — standard setup',
        self::BASIC => 'Basic custom theme',
        self::ELEGANT => 'Elegant custom theme',
        self::PREMIUM => 'Premium custom theme',
    ];

    /** @var array<string, float> */
    private const DEFAULT_PRICES = [
        self::NONE => 0.0,
        self::BASIC => 1200.0,
        self::ELEGANT => 2900.0,
        self::PREMIUM => 4000.0,
    ];

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::LABELS);
    }

    /** The current price for a tier — never a literal at the call site. */
    public static function priceFor(string $key): float
    {
        $key = self::isValid($key) ? $key : self::NONE;

        return (float) Setting::getValue("whitelabel.theme_addon.price.$key", self::DEFAULT_PRICES[$key]);
    }

    public static function labelFor(string $key): string
    {
        return self::isValid($key) ? self::LABELS[$key] : self::LABELS[self::NONE];
    }

    /**
     * Every option including None, in a fixed display order, with its
     * current (possibly admin-retuned) price already resolved.
     *
     * @return array<string, array{label:string, price:float}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::LABELS as $key => $label) {
            $out[$key] = ['label' => $label, 'price' => self::priceFor($key)];
        }

        return $out;
    }
}
