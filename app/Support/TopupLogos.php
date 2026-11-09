<?php

namespace App\Support;

/**
 * Operator logos for the Top-Up section, ALL committed assets (public/images/topup/logos/*.webp, indexed by
 * database/seeders/topup-logos.json): no page ever depends on a third-party host. An operator without a logo gets an
 * honest initials tile from the view, never a blank slot.
 */
class TopupLogos
{
    /** Names suppliers use for the same network. */
    private const ALIASES = ['9mobile' => 'ninemobile', 'airteltigo' => 'airteltigo', 'telecelformerlyvodafone' => 'telecel'];

    /** @var array<string, string>|null key => file */
    private static ?array $index = null;

    /** @var array<string, string>|null key => edge colour of a logo that is full-bleed (so its tile can match it and show clean curves) */
    private static ?array $bgIndex = null;

    public static function key(string $name): string
    {
        $n = preg_replace('/\s*\(.*?\)\s*/', '', $name) ?? $name;
        $k = preg_replace('/[^a-z0-9]/', '', strtolower($n)) ?? '';

        return self::ALIASES[$k] ?? $k;
    }

    /** @return array<string, string> */
    public static function index(): array
    {
        if (self::$index === null) {
            self::$index = [];
            self::$bgIndex = [];
            $f = base_path('database/seeders/topup-logos.json');
            foreach (is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [] as $e) {
                if (! empty($e['key']) && ! empty($e['file'])) {
                    self::$index[$e['key']] = $e['file'];
                    if (! empty($e['bg'])) {
                        self::$bgIndex[$e['key']] = $e['bg'];
                    }
                }
            }
        }

        return self::$index;
    }

    /** Logos the admin uploaded (Admin > Top-ups > Logos): key => public URL. They win over the shipped set. */
    private static function overrides(): array
    {
        static $o = null;
        try {
            return $o ??= (array) \App\Models\Setting::getValue('topup.logo_overrides', []);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function url(?string $operator, ?string $country = null): ?string
    {
        if (! $operator) {
            return null;
        }
        $k = self::resolveKey($operator, $country);
        if ($u = self::overrides()[$k] ?? null) {
            return $u;
        }
        $file = self::index()[$k] ?? null;

        if (! $file || ! is_file(public_path('images/topup/logos/'.$file))) {
            return null;
        }

        // A root-relative path, never scheme://host: the landing payload is cached for an hour (and built by queue/CLI where APP_URL may be wrong), so an absolute URL
        // baked into it would point at the wrong host and every logo would load blank. The browser resolves the path against the page it is on.
        return (string) parse_url(asset('images/topup/logos/'.$file), PHP_URL_PATH);
    }

    /** Words suppliers add to a network's name that are not part of the brand ("MTN Nigeria Data", "Airtel NG Airtime"). */
    private const NOISE = ['data', 'airtime', 'bundle', 'bundles', 'plan', 'plans', 'topup', 'prepaid', 'postpaid', 'recharge', 'mobile money'];

    /** @var array<string,string> */
    private static array $resolved = [];

    /**
     * The logo key for a supplier's operator name. Tries the name as given, then without the country ("MTN Nigeria" -> "mtn"), then without filler words, then the
     * longest known brand the name starts with. Always lower-case letters/digits; falls back to the plain key when nothing matches (the view then shows initials).
     */
    public static function resolveKey(string $operator, ?string $country = null): string
    {
        $cc = strtoupper((string) $country);
        $memo = $cc.'|'.$operator;
        if (isset(self::$resolved[$memo])) {
            return self::$resolved[$memo];
        }
        $index = self::index();
        $k = self::key($operator);
        $candidates = [$k];
        $name = strtolower(preg_replace('/\s*\(.*?\)\s*/', ' ', $operator) ?? $operator);
        $strip = array_filter([$cc !== '' ? strtolower(\App\Support\CountryNames::name($cc)) : null, $cc !== '' ? strtolower($cc) : null, ...self::NOISE]);
        $bare = $name;
        foreach ($strip as $w) {
            $bare = preg_replace('/(^|[\s\-_\/])'.preg_quote($w, '/').'(?=$|[\s\-_\/])/u', ' ', $bare) ?? $bare;
        }
        $candidates[] = self::key($bare);
        $candidates[] = self::key(preg_replace('/\b(nigeria|ghana|kenya|uganda|tanzania|rwanda|zambia|cameroon|senegal|ng|gh|ke|ug|tz|rw|zm)\b/', ' ', $bare) ?? $bare);
        $hit = null;
        if ($cc === 'NG' && in_array('etisalat', $candidates, true)) {
            $hit = 'ninemobile';                   // in Nigeria "Etisalat" is today's 9mobile
        }
        foreach ($hit === null ? $candidates : [] as $c) {
            if ($c !== '' && isset($index[$c])) {
                $hit = $c;
                break;
            }
        }
        if ($hit === null) {
            $best = '';
            foreach (array_keys($index) as $brand) {
                if (strlen($brand) >= 3 && strlen($brand) > strlen($best) && str_starts_with($candidates[1] ?: $k, $brand)) {
                    $best = $brand;
                }
            }
            $hit = $best !== '' ? $best : null;
        }

        return self::$resolved[$memo] = $hit ?? $k;
    }

    /** The edge colour of a full-bleed logo (MTN yellow, Cell C black...), so its tile is that colour and the rounded corners are clean; null for a logo that sits on white. */
    public static function bg(?string $operator, ?string $country = null): ?string
    {
        if (! $operator || self::url($operator, $country) === null) {
            return null;
        }
        self::index();

        return self::$bgIndex[self::resolveKey($operator, $country)] ?? null;
    }

    public static function initials(string $operator): string
    {
        $words = preg_split('/[\s\-\/]+/', trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $operator) ?? $operator)) ?: [];
        $i = '';
        foreach (array_slice(array_filter($words), 0, 2) as $w) {
            $i .= mb_strtoupper(mb_substr($w, 0, 1));
        }

        return $i ?: '?';
    }

    public static function forgetCache(): void
    {
        self::$index = null;
        self::$resolved = [];
        \Illuminate\Support\Facades\Cache::forget('topup.logo.keys');
    }
}
