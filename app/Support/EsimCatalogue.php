<?php

namespace App\Support;

use App\Models\EsimCountryImage;
use App\Models\EsimPlan;
use App\Models\EsimRegionImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache-backed navigation data for the customer eSIM browser (BUILD-8 §3).
 *
 * Everything here reads ONLY from the already-synced esim_plans table and the
 * two admin image tables — never a live provider call (§3.5.3) — and is cached
 * like NumberCatalogue (Cache::rememberForever, busted on the next sync or on an
 * admin image change). The grid stores raw USD teaser amounts + counts; the view
 * formats them into the viewer's currency per-request, so a currency change never
 * needs a re-query.
 *
 * "Local", "Regional" and "Global" mirror EsimPlan::coverage_type. A plan whose
 * coverage_type is still null (a row synced before BUILD-8, not yet re-synced) is
 * inferred here from its country count so the grid is never empty in the gap.
 */
class EsimCatalogue
{
    private const CACHE = 'esim.nav.grid.v1:'; // + data|full

    private const IMG_COUNTRY = 'esim.nav.country_images.v1';

    private const IMG_REGION = 'esim.nav.region_images.v1';

    /** Regional plans a provider left unlabelled land under this fallback tile. */
    public const REGION_OTHER = 'other';

    /**
     * The navigation grid for one line (data-only vs Full eSIM):
     *   [
     *     'local'   => [ ['code'=>'NG','name'=>'Nigeria','from_usd'=>3.5,'count'=>4,'icon'=>?url], ... ],
     *     'regions' => [ ['slug'=>'europe','label'=>'Europe','from_usd'=>9.0,'count'=>7,'icon'=>?url], ... ],
     *     'global'  => ['from_usd'=>19.0,'count'=>2,'icon'=>?url] | null,
     *   ]
     *
     * @return array{local: list<array<string,mixed>>, regions: list<array<string,mixed>>, global: ?array<string,mixed>}
     */
    public static function grid(bool $hasVoice): array
    {
        $raw = Cache::rememberForever(self::CACHE.($hasVoice ? 'full' : 'data'),
            fn () => self::buildGrid($hasVoice));

        // Decorate with admin images at read time (images cached separately so an
        // image upload busts only the small image maps, not the whole grid).
        $countryImages = self::countryIconMap();
        $regionImages = self::regionIconMap();

        $raw['local'] = array_map(function ($row) use ($countryImages) {
            $row['icon'] = $countryImages[$row['code']] ?? null;

            return $row;
        }, $raw['local']);

        $raw['regions'] = array_map(function ($row) use ($regionImages) {
            $row['icon'] = $regionImages[$row['slug']] ?? null;

            return $row;
        }, $raw['regions']);

        if ($raw['global']) {
            $raw['global']['icon'] = $regionImages[EsimRegions::WORLD] ?? null;
        }

        return $raw;
    }

    /**
     * @return array{local: list<array<string,mixed>>, regions: list<array<string,mixed>>, global: ?array<string,mixed>}
     */
    private static function buildGrid(bool $hasVoice): array
    {
        $local = [];   // iso => ['count'=>int,'from'=>float]
        $regions = []; // slug => ['count'=>int,'from'=>float]
        $global = ['count' => 0, 'from' => null];

        EsimPlan::query()
            ->where('is_active', true)
            ->where('has_voice', $hasVoice)
            ->get(['coverage_type', 'region_slug', 'countries', 'final_retail_usd'])
            ->each(function (EsimPlan $p) use (&$local, &$regions, &$global) {
                $price = (float) $p->final_retail_usd;
                $isos = collect((array) $p->countries)
                    ->map(fn ($c) => strtoupper((string) $c))
                    ->filter(fn ($c) => strlen($c) === 2)
                    ->values();

                $coverage = $p->coverage_type
                    ?? ($isos->count() <= 1 ? EsimPlan::COVERAGE_LOCAL : EsimPlan::COVERAGE_REGIONAL);

                if ($coverage === EsimPlan::COVERAGE_GLOBAL) {
                    $global['count']++;
                    $global['from'] = self::min($global['from'], $price);

                    return;
                }

                if ($coverage === EsimPlan::COVERAGE_REGIONAL) {
                    $slug = $p->region_slug ?: self::REGION_OTHER;
                    $regions[$slug] ??= ['count' => 0, 'from' => null];
                    $regions[$slug]['count']++;
                    $regions[$slug]['from'] = self::min($regions[$slug]['from'], $price);

                    return;
                }

                // Local: tally every ISO the plan reaches (usually exactly one).
                foreach ($isos as $iso) {
                    $local[$iso] ??= ['count' => 0, 'from' => null];
                    $local[$iso]['count']++;
                    $local[$iso]['from'] = self::min($local[$iso]['from'], $price);
                }
            });

        // Shape + sort.
        $localRows = [];
        foreach ($local as $iso => $agg) {
            $localRows[] = ['code' => $iso, 'name' => CountryNames::name($iso), 'from_usd' => $agg['from'], 'count' => $agg['count']];
        }
        usort($localRows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $regionRows = [];
        foreach ($regions as $slug => $agg) {
            $regionRows[] = [
                'slug' => $slug,
                'label' => $slug === self::REGION_OTHER ? 'Other regions' : EsimRegions::label($slug),
                'from_usd' => $agg['from'],
                'count' => $agg['count'],
            ];
        }
        // Canonical region order first (world excluded — it's the Global tab), then any extras/other.
        $order = array_flip(EsimRegions::slugs());
        usort($regionRows, fn ($a, $b) => ($order[$a['slug']] ?? 999) <=> ($order[$b['slug']] ?? 999) ?: strcmp($a['label'], $b['label']));

        return [
            'local' => $localRows,
            'regions' => $regionRows,
            'global' => $global['count'] > 0 ? ['from_usd' => $global['from'], 'count' => $global['count']] : null,
        ];
    }

    /** Cheapest of two nullable prices. */
    private static function min(?float $a, float $b): float
    {
        return $a === null ? $b : min($a, $b);
    }

    /** @return array<string,?string> ISO2(upper) => icon_path url */
    private static function countryIconMap(): array
    {
        return Cache::rememberForever(self::IMG_COUNTRY, fn () => EsimCountryImage::query()
            ->pluck('icon_path', 'country_code')
            ->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => $v])
            ->all());
    }

    /** @return array<string,?string> region_slug => icon_path url */
    private static function regionIconMap(): array
    {
        return Cache::rememberForever(self::IMG_REGION, fn () => EsimRegionImage::query()
            ->pluck('icon_path', 'region_slug')->all());
    }

    /** The banner (detail) image for a country, or null (clean fallback). */
    public static function countryBanner(string $iso): ?string
    {
        return EsimCountryImage::where('country_code', strtoupper($iso))->value('detail_image_path');
    }

    /** The banner (detail) image for a region/global slug, or null. */
    public static function regionBanner(string $slug): ?string
    {
        return EsimRegionImage::where('region_slug', $slug)->value('detail_image_path');
    }

    /**
     * "Popular Destinations" — the photo-card row on the eSIM Trending tab.
     *
     * A destination shows when it is EITHER actually bought (has eSIM orders on
     * the active line) OR admin-featured (is_featured) — matching the owner's
     * rule: "most bought countries by volume, but when there's no record just
     * show popular countries by default." Ranking is purchase volume first, then
     * the featured flag, then cheapest price, then name — so real demand leads
     * and, with zero sales, the curated featured set fills the row.
     *
     * Only single-country LOCAL plans define a destination card (a multi-country
     * regional/global plan is not one destination — the Regions/Global tabs cover
     * those), and everything reads from already-synced data + the same
     * admin-editable per-country image (EsimCountryImage.detail_image_path) every
     * other eSIM surface uses — no new admin curation surface.
     *
     * @return list<array{code:string,name:string,from_usd:?float,data_mb:?int,validity_days:?int,count:int,photo:?string}>
     */
    public static function popularDestinations(bool $hasVoice, int $limit = 12): array
    {
        // Teaser (price/data/validity + featured flag) for every country with an
        // active single-country local plan — cached with the rest of the nav grid
        // (busted on sync / admin image change).
        $teasers = Cache::rememberForever(self::CACHE.'popular_teasers:'.($hasVoice ? 'full' : 'data'),
            fn () => self::buildCountryTeasers($hasVoice));

        if ($teasers === []) {
            return [];
        }

        // Live purchase volume per destination (cheap 2-query aggregate, cached
        // briefly since it changes with every sale — not on the sync-bust path).
        $volume = self::purchaseVolumeByCountry($hasVoice);

        // Display set = countries that are bought OR featured (never the whole
        // catalogue — an un-bought, un-featured country stays off the row).
        $codes = array_values(array_filter(array_keys($teasers),
            fn ($iso) => ($volume[$iso] ?? 0) > 0 || ! empty($teasers[$iso]['featured'])));

        // Day-one fallback: zero sales AND nothing admin-featured yet must still
        // show SOMETHING (the doc block above promises exactly this) — every
        // country with an active local plan becomes eligible, cheapest-first,
        // rather than leaving the row silently empty until the first sale or
        // the first admin visits the featured toggle.
        if ($codes === []) {
            $codes = array_keys($teasers);
        }

        usort($codes, function ($a, $b) use ($teasers, $volume) {
            return ($volume[$b] ?? 0) <=> ($volume[$a] ?? 0)                       // most bought first
                ?: (int) ($teasers[$b]['featured']) <=> (int) ($teasers[$a]['featured']) // then curated-popular
                ?: ($teasers[$a]['from'] ?? INF) <=> ($teasers[$b]['from'] ?? INF)  // then cheapest
                ?: strcmp($teasers[$a]['name'], $teasers[$b]['name']);
        });

        $photos = self::countryPhotoMap();
        $out = [];
        foreach (array_slice($codes, 0, $limit) as $iso) {
            $t = $teasers[$iso];
            $out[] = [
                'code' => $iso,
                'name' => $t['name'],
                'from_usd' => $t['from'],
                'data_mb' => $t['data_mb'],
                'validity_days' => $t['validity_days'],
                'count' => $t['count'],
                'photo' => $photos[$iso] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Per-country teaser for every country reachable by an active single-country
     * local plan on the line: cheapest price + its data/validity, plan count, and
     * whether any of its plans is admin-featured.
     *
     * @return array<string, array{name:string,from:?float,data_mb:?int,validity_days:?int,count:int,featured:bool}>
     */
    private static function buildCountryTeasers(bool $hasVoice): array
    {
        $byCountry = [];

        EsimPlan::query()
            ->where('is_active', true)
            ->where('has_voice', $hasVoice)
            ->where(function ($q) {
                $q->where('coverage_type', EsimPlan::COVERAGE_LOCAL)->orWhereNull('coverage_type');
            })
            ->get(['countries', 'final_retail_usd', 'data_mb', 'validity_days', 'is_featured'])
            ->each(function (EsimPlan $p) use (&$byCountry) {
                $isos = collect((array) $p->countries)
                    ->map(fn ($c) => strtoupper((string) $c))
                    ->filter(fn ($c) => strlen($c) === 2)
                    ->values();
                if ($isos->count() !== 1) {
                    return; // multi-country → not a single destination card
                }
                $iso = $isos->first();
                $price = (float) $p->final_retail_usd;

                $byCountry[$iso] ??= ['name' => CountryNames::name($iso), 'from' => null, 'data_mb' => null, 'validity_days' => null, 'count' => 0, 'featured' => false];
                $byCountry[$iso]['count']++;
                $byCountry[$iso]['featured'] = $byCountry[$iso]['featured'] || (bool) $p->is_featured;
                if ($byCountry[$iso]['from'] === null || $price < $byCountry[$iso]['from']) {
                    $byCountry[$iso]['from'] = $price;
                    $byCountry[$iso]['data_mb'] = $p->data_mb;
                    $byCountry[$iso]['validity_days'] = $p->validity_days;
                }
            });

        return $byCountry;
    }

    /**
     * eSIM order volume per single-country destination on the line. Two cheap
     * queries (plan→country map, then grouped order counts); cached briefly since
     * it moves with every sale. Failed/cancelled orders are not "bought."
     *
     * @return array<string, int> ISO2(upper) => order count
     */
    private static function purchaseVolumeByCountry(bool $hasVoice): array
    {
        return Cache::remember(self::CACHE.'popular_vol:'.($hasVoice ? 'full' : 'data'), now()->addMinutes(30), function () use ($hasVoice) {
            $planToIso = [];
            EsimPlan::query()
                ->where('has_voice', $hasVoice)
                ->where(function ($q) {
                    $q->where('coverage_type', EsimPlan::COVERAGE_LOCAL)->orWhereNull('coverage_type');
                })
                ->get(['id', 'countries'])
                ->each(function (EsimPlan $p) use (&$planToIso) {
                    $isos = collect((array) $p->countries)
                        ->map(fn ($c) => strtoupper((string) $c))
                        ->filter(fn ($c) => strlen($c) === 2)
                        ->values();
                    if ($isos->count() === 1) {
                        $planToIso[$p->id] = $isos->first();
                    }
                });

            if ($planToIso === []) {
                return [];
            }

            $counts = DB::table('esim_orders')
                ->whereIn('plan_id', array_keys($planToIso))
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->selectRaw('plan_id, count(*) as c')
                ->groupBy('plan_id')
                ->pluck('c', 'plan_id');

            $vol = [];
            foreach ($counts as $planId => $c) {
                $iso = $planToIso[$planId] ?? null;
                if ($iso !== null) {
                    $vol[$iso] = ($vol[$iso] ?? 0) + (int) $c;
                }
            }

            return $vol;
        });
    }

    /** @return array<string,?string> ISO2(upper) => detail_image_path url */
    private static function countryPhotoMap(): array
    {
        return Cache::rememberForever(self::IMG_COUNTRY.'.photo', fn () => EsimCountryImage::query()
            ->pluck('detail_image_path', 'country_code')
            ->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => $v])
            ->all());
    }

    /** Bust every navigation cache (call after a sync or an admin image change). */
    public static function flush(): void
    {
        Cache::forget(self::CACHE.'data');
        Cache::forget(self::CACHE.'full');
        Cache::forget(self::CACHE.'popular_teasers:data');
        Cache::forget(self::CACHE.'popular_teasers:full');
        Cache::forget(self::CACHE.'popular_vol:data');
        Cache::forget(self::CACHE.'popular_vol:full');
        Cache::forget(self::IMG_COUNTRY);
        Cache::forget(self::IMG_COUNTRY.'.photo');
        Cache::forget(self::IMG_REGION);
    }
}
