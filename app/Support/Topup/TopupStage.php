<?php

namespace App\Support\Topup;

use App\Models\TopupProduct;
use App\Support\CountryNames;
use App\Support\TopupLogos;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The countries and networks the Airtime and Data hero showcases, read from the live catalogue so the hero can never show a network the product
 * does not sell. Only names and logos: no price, no cost. Cached for an hour.
 */
final class TopupStage
{
    public const CACHE_KEY = 'topup.stage.v1';

    /** Countries shown first (a world mix); those without a synced catalogue are skipped. */
    private const FEATURED = ['NG', 'GB', 'US', 'IN', 'GH', 'AE', 'BR', 'KE', 'MX', 'PH', 'ZA', 'PK', 'DE', 'ID', 'TR', 'BD', 'FR', 'CA', 'UG', 'TZ', 'CM', 'SN', 'CI', 'RW', 'ZM'];

    /** @return array{countries: list<array{iso:string,name:string,networks_total:int,networks:list<array>}>} */
    public static function data(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => self::build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function build(): array
    {
        try {
            $rows = TopupProduct::query()->where('active', true)->select('country_iso', 'operator_name')->distinct()->get();
            $by = [];
            foreach ($rows as $r) {
                if (preg_match('/esim|tinder|subscription/i', (string) $r->operator_name)) {
                    continue;
                }
                $by[$r->country_iso][strtolower(trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $r->operator_name)))] = $r->operator_name;
            }
            $order = collect(self::FEATURED)->filter(fn ($c) => isset($by[$c]))->merge(collect($by)->map(fn ($o) => count($o))->sortDesc()->keys())->unique()->take(40);
            $countries = [];
            foreach ($order as $iso) {
                $ops = collect($by[$iso])->values();
                $nets = $ops->map(fn ($n) => ['name' => $n, 'logo' => TopupLogos::url($n, $iso), 'bg' => TopupLogos::bg($n, $iso), 'initials' => TopupLogos::initials($n)])
                    ->sortByDesc(fn ($n) => $n['logo'] ? 1 : 0)->values()->take(8)->all();
                if (count($nets) >= 2) {
                    $countries[] = ['iso' => $iso, 'name' => CountryNames::name($iso), 'networks_total' => $ops->count(), 'networks' => $nets];
                }
            }

            return ['countries' => $countries];
        } catch (Throwable $e) {
            report($e);

            return ['countries' => []];
        }
    }
}
