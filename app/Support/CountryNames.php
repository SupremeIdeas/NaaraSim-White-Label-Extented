<?php

namespace App\Support;

/**
 * ISO-3166 alpha-2 → display name, via the intl extension (no hardcoded 250-row
 * map to drift). Accepts the same slugs/codes as CountryFlags, so 'ng' / 'NG' /
 * 'nigeria' all resolve to "Nigeria". Unknown → a tidy title-cased fallback.
 */
class CountryNames
{
    /** Codes providers use that are not countries, and ICU names spelled out in full for display (no "&", no "Congo - Kinshasa"). */
    private const SPECIAL = ['XI' => 'International'];

    private const SPELLED = ['Congo - Kinshasa' => 'DR Congo', 'Congo - Brazzaville' => 'Republic of the Congo', 'St. ' => 'Saint '];

    /** Human country name for an ISO2 code or provider slug, spelled out in full. */
    public static function name(?string $country): string
    {
        $raw = self::resolve($country);
        $raw = str_replace(' & ', ' and ', $raw);
        foreach (self::SPELLED as $from => $to) {
            $raw = str_starts_with($raw, $from) || $raw === $from ? $to.substr($raw, strlen($from)) : $raw;
        }

        return $raw;
    }

    private static function resolve(?string $country): string
    {
        if ($country !== null && isset(self::SPECIAL[strtoupper(trim($country))])) {
            return self::SPECIAL[strtoupper(trim($country))];
        }

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
