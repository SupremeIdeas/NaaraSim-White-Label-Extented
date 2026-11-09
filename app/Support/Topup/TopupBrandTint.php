<?php

namespace App\Support\Topup;

/** The brand colour of a network, so the top-up hero can take on the colour of the service on the phone. Unknown networks get a stable, tasteful hue. */
final class TopupBrandTint
{
    private const BRANDS = [
        'mtn' => '#FFC400', 'airtel' => '#E4002B', 'glo' => '#2E9F4F', '9mobile' => '#0B7A4B', 'nine mobile' => '#0B7A4B', 'etisalat' => '#0B7A4B', 'vodafone' => '#E60000', 'vodacom' => '#E60000',
        'safaricom' => '#43B02A', 'telkom' => '#0A8BD6', 'orange' => '#FF7900', 'tigo' => '#1A4FBA', 'airteltigo' => '#E4002B', 'movistar' => '#019DF4', 'claro' => '#DA291C',
        't-mobile' => '#E20074', 'at&t' => '#00A8E0', 'verizon' => '#CD040B', 'jazz' => '#D9232E', 'telenor' => '#00A3E0', 'ufone' => '#F26A21', 'zong' => '#7AC143',
        'digicel' => '#E4002B', 'cell c' => '#222222', 'econet' => '#1D4ED8', 'ncell' => '#1E88E5', 'dialog' => '#E4002B', 'globe' => '#0B63CE', 'smart' => '#1BA94C',
        'indosat' => '#F5A800', 'telkomsel' => '#E4002B', 'xl' => '#0F6FD0', 'vivo' => '#7A1FA2', 'tim' => '#0033A0', 'turkcell' => '#FFC72C', 'ee' => '#00A4A6',
        'o2' => '#0019A5', 'three' => '#222222', 'lycamobile' => '#E4002B', 'mtc' => '#E4002B', 'beeline' => '#FFC400', 'megafon' => '#00B956', 'zain' => '#8DC63F',
        'ooredoo' => '#E4002B', 'etisalat uae' => '#4CAF50', 'du' => '#0A9DD9', 'stc' => '#5B2D8C', 'mobily' => '#6FB43F', 'moov' => '#0067B1', 'camtel' => '#0A8F3C',
        'liberty' => '#E4002B', 'africell' => '#8E24AA', 'halotel' => '#F57C00', 'rwandatel' => '#0A8BD6', 'lumitel' => '#E4002B',
    ];

    /** Logo keys whose picture gives a muddy colour: the real brand colour. */
    private const FIXES = ['safaricom' => '#43B02A', 'tmobile' => '#E20074', 'verizon' => '#CD040B', 'verizonwireless' => '#CD040B', 'africell' => '#8E24AA', 'smartfren' => '#E4002B',
        'inwi' => '#7B2D8E', 'bait' => '#F39200', 'gmobile' => '#2A4DD0', 'smile' => '#00A99D', 'cellcom' => '#0A6EBD'];

    /** @var array<string, string>|null logo key => dominant colour read from the shipped logo picture (database/seeders/topup-brand-colors.json) */
    private static ?array $fromLogos = null;

    /** WCAG relative luminance of #RRGGBB. */
    public static function luminance(string $hex): float
    {
        $c = array_map(fn ($i) => hexdec(substr(ltrim($hex, '#'), $i, 2)) / 255, [0, 2, 4]);
        $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** WCAG contrast ratio between two #RRGGBB colours (1 to 21). */
    public static function contrast(string $a, string $b): float
    {
        [$x, $y] = [self::luminance($a), self::luminance($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    public const INK_DARK = '#0A0D10';

    public const INK_LIGHT = '#FFFFFF';

    /** The text colour that reads best ON this colour. Always at least 4.5:1 (the better of near-black and white can never fall below about 4.58). */
    public static function ink(string $hex): string
    {
        return self::contrast($hex, self::INK_DARK) >= self::contrast($hex, self::INK_LIGHT) ? self::INK_DARK : self::INK_LIGHT;
    }

    /** A brand colour in the narrow mid-grey band where neither black nor white text reaches 4.5:1 is moved a touch darker until one does; every other colour is returned as it is. */
    public static function safe(string $hex): string
    {
        $hex = strtoupper($hex);
        $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)), [1, 3, 5]);
        for ($i = 0; $i < 40 && self::contrast($hex, self::ink($hex)) < 4.5; $i++) {
            $c = array_map(fn ($v) => (int) max(0, round($v * 0.97)), $c);
            $hex = sprintf('#%02X%02X%02X', ...$c);
        }

        return $hex;
    }

    /** The brand colour moved the SMALLEST amount (toward black for white ink, toward white for dark ink) until its ink reads at `$target`:1. Colours that already pass are returned as they are. */
    public static function reach(string $hex, float $target = 5.5): string
    {
        $hex = strtoupper($hex);
        $best = null;
        foreach ([[self::INK_LIGHT, '#000000'], [self::INK_DARK, '#FFFFFF']] as [$ink, $toward]) {
            for ($t = 0.0; $t <= 1.0001; $t += 0.02) {
                $c = $t == 0.0 ? $hex : self::mix($hex, $toward, $t);
                if (self::contrast($c, $ink) >= $target) {
                    if ($best === null || $t < $best[0]) {
                        $best = [$t, $c];
                    }
                    break;
                }
            }
        }

        return $best[1] ?? $hex;
    }

    private static function mix(string $a, string $b, float $t): string
    {
        $x = array_map(fn ($i) => hexdec(substr($a, $i, 2)), [1, 3, 5]);
        $y = array_map(fn ($i) => hexdec(substr($b, $i, 2)), [1, 3, 5]);

        return sprintf('#%02X%02X%02X', ...array_map(fn ($i) => (int) round($x[$i] * (1 - $t) + $y[$i] * $t), [0, 1, 2]));
    }

    /**
     * The hero colours for one network, for each mode. Every colour in a palette (base and both aurora tones) keeps `ink` at 4.5:1 or better, so the text is
     * readable ANYWHERE on the hero, with no scrim needed.
     *  - light: the brand colour itself (softened a touch) with a LIGHT aurora (the brand lifted toward white, never past what the ink allows).
     *  - dark:  near-black tinted by the brand with a brand-and-black aurora (the brand share is capped so white text always reads), e.g. MTN gold on black.
     *
     * @return array{light: array{base: string, a1: string, a2: string, ink: string}, dark: array{base: string, a1: string, a2: string, ink: string}}
     */
    public static function palette(string $brand): array
    {
        $brand = strtoupper($brand);
        $b = self::reach(self::mix($brand, '#FFFFFF', 0.04), 5.5);
        $ink = self::ink($b);
        $a1 = $b;
        for ($t = 0.32; $t > 0.0; $t -= 0.02) {
            $c = self::mix($b, '#FFFFFF', $t);
            if (self::contrast($c, $ink) >= 5.5) {
                $a1 = $c;
                break;
            }
        }
        $a2 = $b;
        for ($u = 0.16; $u > 0.0; $u -= 0.02) {
            $c = self::mix($b, '#000000', $u);
            if (self::contrast($c, $ink) >= 5.5) {
                $a2 = $c;
                break;
            }
        }
        $light = ['base' => $b, 'a1' => $a1, 'a2' => $a2, 'ink' => $ink];

        $white = self::INK_LIGHT;
        $d1 = '#000000';
        for ($w = 0.8; $w > 0.0; $w -= 0.04) {
            $c = self::mix('#06080B', $brand, $w);
            if (self::contrast($c, $white) >= 7.0) {
                $d1 = $c;
                break;
            }
        }
        $dark = ['base' => self::mix('#06080B', $brand, 0.1), 'a1' => $d1, 'a2' => self::mix('#06080B', $d1, 0.5), 'ink' => $white];

        return ['light' => $light, 'dark' => $dark];
    }

    /** The far end of a card gradient: moves AWAY from the ink so the text only gets more readable across the card. */
    public static function gradientEnd(string $hex): string
    {
        $towards = self::ink($hex) === self::INK_LIGHT ? 0 : 255;
        $c = array_map(fn ($i) => (int) round(hexdec(substr(ltrim($hex, '#'), $i, 2)) * 0.74 + $towards * 0.26), [0, 2, 4]);

        return sprintf('#%02X%02X%02X', ...$c);
    }

    /** @var array<string, array<string, mixed>>|null brand family => {hex, ink, source, countries{ISO => hex}} (database/seeders/topup-brand-families.json: researched from official brand guidelines) */
    private static ?array $families = null;

    /** "Orange Liberia Bundle" + LR -> "orange": the brand family, with the country and the product words taken off. */
    public static function family(string $network, ?string $country = null): string
    {
        $n = strtolower(preg_replace('/\s*\(.*?\)\s*/', ' ', $network) ?? $network);
        $n = preg_replace('/[^a-z0-9 ]/', '', $n) ?? $n;
        if ($country) {
            $cn = strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', \App\Support\CountryNames::name($country)) ?? '');
            if ($cn !== '') {
                $n = preg_replace('/\b'.preg_quote($cn, '/').'\b/', ' ', $n) ?? $n;
            }
        }
        $n = preg_replace('/\b(bundles?|bonus|data|airtime|super ?recarga|super ?pack|paquetes?|prepaid plans?|recarga|pin|pinless|monthly|paygo|rtr|more credit)\b/', ' ', $n) ?? $n;

        return trim(preg_replace('/\s+/', ' ', $n) ?? $n);
    }

    /** The colour for a network: the researched official colour of its brand family (a country can have its own), then the colour read from its logo, then the curated list, then a stable hue. */
    public static function for(string $network, ?string $country = null): string
    {
        return self::reach(self::safe(self::resolve($network, $country)), 5.5);
    }

    private static function resolve(string $network, ?string $country): string
    {
        self::$families ??= (array) json_decode((string) @file_get_contents(base_path('database/seeders/topup-brand-families.json')), true);
        $fam = self::family($network, $country);
        $row = self::$families[$fam] ?? self::$families[str_replace(' ', '', $fam)] ?? null;
        if (is_array($row)) {
            $hex = ($country ? ($row['countries'][strtoupper($country)] ?? null) : null) ?? ($row['hex'] ?? null);
            if (is_string($hex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                return strtoupper($hex);
            }
        }
        $key = \App\Support\TopupLogos::key($network);
        if (isset(self::FIXES[$key])) {
            return self::FIXES[$key];
        }
        self::$fromLogos ??= (array) json_decode((string) @file_get_contents(base_path('database/seeders/topup-brand-colors.json')), true);
        if (isset(self::$fromLogos[$key])) {
            return strtoupper(self::$fromLogos[$key]);
        }

        return self::curated($network);
    }

    private static function curated(string $network): string
    {
        $n = strtolower(trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $network) ?? $network));
        $best = null;
        foreach (self::BRANDS as $k => $hex) {
            if (is_int($k)) {
                continue;
            }
            if (str_contains($n, (string) $k) && ($best === null || strlen((string) $k) > strlen($best[0]))) {
                $best = [(string) $k, $hex];
            }
        }
        if ($best) {
            return $best[1];
        }
        $h = crc32($n) % 360;

        return self::hsl($h, 62, 46);
    }

    private static function hsl(int $h, int $s, int $l): string
    {
        $s /= 100; $l /= 100;
        $a = $s * min($l, 1 - $l);
        $f = function ($n) use ($h, $l, $a) {
            $k = fmod($n + $h / 30, 12);

            return $l - $a * max(-1, min($k - 3, 9 - $k, 1));
        };

        return sprintf('#%02X%02X%02X', round($f(0) * 255), round($f(8) * 255), round($f(4) * 255));
    }
}
