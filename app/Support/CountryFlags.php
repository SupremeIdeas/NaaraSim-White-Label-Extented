<?php

namespace App\Support;

/**
 * Country-flag resolution (Module 27.5). Flags render via the self-hosted
 * flag-icons SVG set (bundled at build — no CDN), addressed by ISO-3166 alpha-2
 * class (`fi fi-ng`). Accepts ISO codes directly (eSIM plans store ['US', …])
 * and the provider-style country slugs the number lanes use ('usa',
 * 'england', …). Unknown → null, and the <x-country-flag> component falls back
 * to a globe icon (never a broken image).
 */
class CountryFlags
{
    /** provider slug / common name => ISO2. */
    private const NAMES = [
        'usa' => 'us', 'unitedstates' => 'us', 'united states' => 'us',
        'uk' => 'gb', 'england' => 'gb', 'unitedkingdom' => 'gb', 'united kingdom' => 'gb',
        'nigeria' => 'ng', 'ghana' => 'gh', 'kenya' => 'ke',
        'southafrica' => 'za', 'south africa' => 'za',
        'canada' => 'ca', 'france' => 'fr', 'germany' => 'de', 'spain' => 'es',
        'italy' => 'it', 'portugal' => 'pt', 'netherlands' => 'nl', 'belgium' => 'be',
        'ireland' => 'ie', 'sweden' => 'se', 'norway' => 'no', 'denmark' => 'dk',
        'poland' => 'pl', 'romania' => 'ro', 'ukraine' => 'ua', 'russia' => 'ru',
        'turkey' => 'tr', 'greece' => 'gr', 'switzerland' => 'ch', 'austria' => 'at',
        'india' => 'in', 'pakistan' => 'pk', 'bangladesh' => 'bd', 'philippines' => 'ph',
        'indonesia' => 'id', 'vietnam' => 'vn', 'thailand' => 'th', 'malaysia' => 'my',
        'singapore' => 'sg', 'china' => 'cn', 'japan' => 'jp', 'southkorea' => 'kr',
        'hongkong' => 'hk', 'taiwan' => 'tw',
        'uae' => 'ae', 'unitedarabemirates' => 'ae', 'saudiarabia' => 'sa',
        'qatar' => 'qa', 'kuwait' => 'kw', 'israel' => 'il', 'egypt' => 'eg',
        'morocco' => 'ma', 'algeria' => 'dz', 'tunisia' => 'tn',
        'ethiopia' => 'et', 'tanzania' => 'tz', 'uganda' => 'ug', 'rwanda' => 'rw',
        'cameroon' => 'cm', 'ivorycoast' => 'ci', 'senegal' => 'sn', 'zambia' => 'zm',
        'zimbabwe' => 'zw', 'botswana' => 'bw', 'mozambique' => 'mz', 'angola' => 'ao',
        'brazil' => 'br', 'mexico' => 'mx', 'argentina' => 'ar', 'colombia' => 'co',
        'chile' => 'cl', 'peru' => 'pe',
        'australia' => 'au', 'newzealand' => 'nz',
    ];

    /** ISO2 code for a country slug/name/code, or null when unknown. */
    public static function iso(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        $normalized = strtolower(trim($country));

        // Already an ISO2 code (eSIM plans store these).
        if (strlen($normalized) === 2 && ctype_alpha($normalized)) {
            return $normalized;
        }

        return self::NAMES[$normalized] ?? self::NAMES[str_replace([' ', '-', '_'], '', $normalized)] ?? null;
    }

    /** flag-icons CSS class, or null. */
    public static function flagClass(?string $country): ?string
    {
        $iso = self::iso($country);

        return $iso ? 'fi fi-'.$iso : null;
    }

    /** Human label for a slug ('usa' => 'United States'). Best-effort. */
    public static function label(string $country): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $country));
    }
}
