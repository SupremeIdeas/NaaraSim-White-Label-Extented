<?php

namespace App\Support;

/**
 * ISO-3166 alpha-2 → international dialling code. Two consumers share this one
 * source:
 *   - the shared CountryPicker's phone-number mode (Numbers V6) via for(),
 *     which returns the "+NNN" form and omits an unknown code rather than
 *     guessing;
 *   - the in-browser dialer's country picker (Numbers overhaul §3) via all() /
 *     default() / codeFor(), which return the digits-only form the dialer
 *     prepends its own '+' to.
 *
 * Accepts the same slugs/codes as CountryFlags (resolves the slug to ISO2
 * first). Names come from CountryNames so there is no second display map to
 * drift; the dialer picker pins NaaraSim's home markets (PRIORITY) to the top.
 */
class DialCodes
{
    /** ISO2 => "+NNN". Covers the number catalogue's countries + the majors. */
    private const CODES = [
        'us' => '+1', 'ca' => '+1', 'gb' => '+44', 'ie' => '+353', 'fr' => '+33',
        'de' => '+49', 'es' => '+34', 'it' => '+39', 'pt' => '+351', 'nl' => '+31',
        'be' => '+32', 'ch' => '+41', 'at' => '+43', 'se' => '+46', 'no' => '+47',
        'dk' => '+45', 'fi' => '+358', 'pl' => '+48', 'cz' => '+420', 'sk' => '+421',
        'hu' => '+36', 'ro' => '+40', 'bg' => '+359', 'gr' => '+30', 'hr' => '+385',
        'rs' => '+381', 'si' => '+386', 'ua' => '+380', 'ru' => '+7', 'kz' => '+7',
        'tr' => '+90', 'lt' => '+370', 'lv' => '+371', 'ee' => '+372', 'is' => '+354',
        'lu' => '+352', 'mt' => '+356', 'cy' => '+357', 'md' => '+373', 'ge' => '+995',
        'am' => '+374', 'az' => '+994',
        'ng' => '+234', 'gh' => '+233', 'ke' => '+254', 'za' => '+27', 'eg' => '+20',
        'ma' => '+212', 'dz' => '+213', 'tn' => '+216', 'et' => '+251', 'tz' => '+255',
        'ug' => '+256', 'rw' => '+250', 'cm' => '+237', 'ci' => '+225', 'sn' => '+221',
        'zm' => '+260', 'zw' => '+263', 'bw' => '+267', 'mz' => '+258', 'ao' => '+244',
        'ml' => '+223', 'bf' => '+226', 'bj' => '+229', 'ne' => '+227', 'td' => '+235',
        'cd' => '+243', 'cg' => '+242', 'ga' => '+241', 'mg' => '+261', 'mw' => '+265',
        'mu' => '+230', 'na' => '+264', 'ly' => '+218', 'sd' => '+249',
        'in' => '+91', 'pk' => '+92', 'bd' => '+880', 'lk' => '+94', 'np' => '+977',
        'ph' => '+63', 'id' => '+62', 'vn' => '+84', 'th' => '+66', 'my' => '+60',
        'sg' => '+65', 'cn' => '+86', 'jp' => '+81', 'kr' => '+82', 'hk' => '+852',
        'tw' => '+886', 'mo' => '+853', 'kh' => '+855', 'la' => '+856', 'mm' => '+95',
        'mn' => '+976', 'af' => '+93', 'uz' => '+998', 'kg' => '+996', 'tj' => '+992',
        'ae' => '+971', 'sa' => '+966', 'qa' => '+974', 'kw' => '+965', 'bh' => '+973',
        'om' => '+968', 'jo' => '+962', 'lb' => '+961', 'il' => '+972', 'iq' => '+964',
        'ir' => '+98', 'ye' => '+967', 'sy' => '+963',
        'br' => '+55', 'mx' => '+52', 'ar' => '+54', 'co' => '+57', 'cl' => '+56',
        'pe' => '+51', 've' => '+58', 'ec' => '+593', 'bo' => '+591', 'py' => '+595',
        'uy' => '+598', 'gt' => '+502', 'cr' => '+506', 'pa' => '+507', 'do' => '+1',
        'hn' => '+504', 'sv' => '+503', 'ni' => '+505', 'jm' => '+1', 'tt' => '+1',
        'au' => '+61', 'nz' => '+64', 'fj' => '+679', 'pg' => '+675',
    ];

    /** Home markets pinned to the top of the dialer picker, in this order. */
    private const PRIORITY = ['ng', 'gh', 'ke', 'za', 'us', 'gb'];

    /** Dial code ("+234") for a country slug/ISO2, or null when unknown. */
    public static function for(?string $country): ?string
    {
        $iso = CountryFlags::iso($country);

        return $iso !== null ? (self::CODES[strtolower($iso)] ?? null) : null;
    }

    /** Digits-only dial code ("234") for a slug/ISO2, or null when unknown. */
    public static function codeFor(?string $country): ?string
    {
        $code = self::for($country);

        return $code !== null ? ltrim($code, '+') : null;
    }

    /**
     * The dialer picker list: priority markets first, then the rest
     * alphabetically by name. Each row is ['iso' => , 'name' => , 'code' => ]
     * with the digits-only code (no '+').
     */
    public static function all(): array
    {
        $rows = [];
        foreach (self::CODES as $iso => $code) {
            $rows[$iso] = ['iso' => $iso, 'name' => CountryNames::name($iso), 'code' => ltrim($code, '+')];
        }

        $priority = [];
        foreach (self::PRIORITY as $iso) {
            if (isset($rows[$iso])) {
                $priority[] = $rows[$iso];
                unset($rows[$iso]);
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return array_values(array_merge($priority, $rows));
    }

    /** The picker row for an ISO2 / slug, defaulting to Nigeria (our home). */
    public static function default(?string $country): array
    {
        $iso = CountryFlags::iso($country);
        if ($iso !== null && isset(self::CODES[strtolower($iso)])) {
            $iso = strtolower($iso);

            return ['iso' => $iso, 'name' => CountryNames::name($iso), 'code' => ltrim(self::CODES[$iso], '+')];
        }

        return ['iso' => 'ng', 'name' => CountryNames::name('ng'), 'code' => ltrim(self::CODES['ng'], '+')];
    }
}
