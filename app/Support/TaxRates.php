<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-configurable tax/VAT rates (BUILD-7 §2). A country ISO => percent map,
 * stored as a single setting and cached. Ships EMPTY — no tax is charged
 * anywhere until an admin explicitly sets a rate for a specific country. Which
 * countries to tax, at what rate, under what registration, is a legal/accounting
 * decision for the operator; this is only the plumbing.
 */
class TaxRates
{
    private const KEY = 'tax.rates';

    private const CACHE = 'tax.rates.v1';

    /** @return array<string, float> upper-case ISO => rate percent */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            try {
                $raw = Setting::getValue(self::KEY, []);
            } catch (\Throwable) {
                return [];
            }
            $out = [];
            foreach ((is_array($raw) ? $raw : []) as $country => $rate) {
                $iso = strtoupper(trim((string) $country));
                $pct = (float) $rate;
                if ($iso !== '' && $pct > 0) {
                    $out[$iso] = round(min(100, $pct), 3);
                }
            }

            return $out;
        });
    }

    /** The configured rate percent for a country, or 0 when none is set. */
    public static function rateFor(?string $country): float
    {
        if ($country === null || $country === '') {
            return 0.0;
        }

        return self::all()[strtoupper($country)] ?? 0.0;
    }

    public static function isConfigured(): bool
    {
        return self::all() !== [];
    }

    /** @param array<string, float|string> $rates */
    public static function save(array $rates): void
    {
        $clean = [];
        foreach ($rates as $country => $rate) {
            $iso = strtoupper(trim((string) $country));
            $pct = round((float) $rate, 3);
            if ($iso !== '' && $pct > 0 && $pct <= 100) {
                $clean[$iso] = $pct;
            }
        }
        Setting::setValue(self::KEY, $clean, 'tax');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }

    public static function isTaxKey(string $key): bool
    {
        return $key === self::KEY;
    }
}
