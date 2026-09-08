<?php

namespace App\Support;

/**
 * ISO-3166 alpha-2 → display name, via the intl extension (no hardcoded 250-row
 * map to drift). Accepts the same slugs/codes as CountryFlags, so 'ng' / 'NG' /
 * 'nigeria' all resolve to "Nigeria". Unknown → a tidy title-cased fallback.
 */
class CountryNames
{
    /** Human country name for an ISO2 code or provider slug. */
    public static function name(?string $country): string
    {
        if ($country === null || trim($country) === '') {
            return '';
        }

        $iso = CountryFlags::iso($country);
        if ($iso !== null && class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.strtoupper($iso), 'en');
            // getDisplayRegion echoes the input back when it can't resolve.
            if ($name !== '' && strtolower($name) !== strtolower($iso)) {
                return $name;
            }
        }

        return CountryFlags::label($country);
    }
}
