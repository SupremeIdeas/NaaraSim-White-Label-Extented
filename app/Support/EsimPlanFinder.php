<?php

namespace App\Support;

use App\Models\EsimPlan;
use App\Support\Niche\DataEstimator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * eSIM Plan Finder: filters and ranks the plans of ONE storefront context (a country, a region, or global, on one line) from what the
 * catalogue already synced. Strictly read-only and money-safe: it selects only id, data, validity and the final retail price (never a
 * cost column), makes no provider call, and never changes a price. The ranking maths lives here (pure, unit-tested); the Livewire
 * Catalogue only asks it for an ordered query and for the badges/"why" lines to print.
 *
 * Plan row shape used throughout: ['id' => int, 'mb' => ?int (null = unlimited), 'days' => ?int, 'price' => float].
 * Criteria shape (see sanitise()): days[], need (GB), unlimited, min, max, usage, trip, estimate, sort.
 */
final class EsimPlanFinder
{
    public const DAY_CHOICES = [1, 3, 5, 7, 10, 15, 30, '30+'];

    public const NEED_CHOICES = [1, 2, 3, 5, 10, 20, 50, 100];

    public const SORTS = ['best', 'price', 'data', 'days', 'pergb'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return ['days' => [], 'need' => null, 'unlimited' => false, 'min' => (float) config('esim_finder.price_min', 1), 'max' => (float) config('esim_finder.price_max', 2000),
            'usage' => 'medium', 'trip' => 30, 'estimate' => false, 'sort' => 'best'];
    }

    /**
     * Never trust the browser: clamp every value to what the UI can legitimately send.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function sanitise(array $raw): array
    {
        $d = self::defaults();
        $lo = (float) config('esim_finder.price_min', 1);
        $hi = (float) config('esim_finder.price_max', 2000);

        $days = [];
        foreach ((array) ($raw['days'] ?? []) as $v) {
            if ($v !== '30+' && ! is_numeric($v)) {
                continue;   // anything that is not a plain number (or "30+") is dropped, not coerced
            }
            $v = $v === '30+' ? '30+' : (int) $v;
            if (in_array($v, self::DAY_CHOICES, true) && ! in_array($v, $days, true)) {
                $days[] = $v;
            }
        }
        $unlimited = (bool) ($raw['unlimited'] ?? false);
        $need = isset($raw['need']) && is_numeric($raw['need']) ? round(max(0.1, min(500.0, (float) $raw['need'])), 1) : null;

        $min = is_numeric($raw['min'] ?? null) ? (float) $raw['min'] : $d['min'];
        $max = is_numeric($raw['max'] ?? null) ? (float) $raw['max'] : $d['max'];
        $min = max($lo, min($hi, $min));
        $max = max($lo, min($hi, $max));
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        $usage = (string) ($raw['usage'] ?? $d['usage']);

        return [
            'days' => $days,
            'need' => $unlimited ? null : $need,
            'unlimited' => $unlimited,
            'min' => $min,
            'max' => $max,
            'usage' => array_key_exists($usage, DataEstimator::PROFILES) ? $usage : $d['usage'],
            'trip' => max(1, min(60, (int) ($raw['trip'] ?? $d['trip']))),
            'estimate' => (bool) ($raw['estimate'] ?? false),
            'sort' => in_array($raw['sort'] ?? 'best', self::SORTS, true) ? (string) ($raw['sort'] ?? 'best') : 'best',
        ];
    }

    /** True when the visitor has narrowed anything down (drives the "N filters" summary). @param array<string, mixed> $c */
    public static function activeCount(array $c): int
    {
        $d = self::defaults();

        return ($c['days'] ? 1 : 0) + (($c['need'] !== null || $c['unlimited']) ? 1 : 0) + ((abs($c['min'] - $d['min']) > 0.001 || abs($c['max'] - $d['max']) > 0.001) ? 1 : 0);
    }

    /**
     * The hard filters. A capped plan has to hold the asked-for data (a 1000 MB plan counts as 1 GB) without being a silly overbuy;
     * an unlimited plan always satisfies a data ask. Days is "any of the chosen validities" ('30+' = longer than 30 days).
     *
     * @param  Collection<int, array{id:int, mb:?int, days:?int, price:float}>  $rows
     * @param  array<string, mixed>  $c
     * @return Collection<int, array{id:int, mb:?int, days:?int, price:float}>
     */
    public static function match(Collection $rows, array $c): Collection
    {
        $cover = (float) config('esim_finder.min_cover', 0.95);
        $over = (float) config('esim_finder.max_overbuy', 6);

        return $rows->filter(function (array $p) use ($c, $cover, $over): bool {
            if ($p['price'] < $c['min'] - 0.0001 || $p['price'] > $c['max'] + 0.0001) {
                return false;
            }
            if ($c['unlimited'] && $p['mb'] !== null) {
                return false;
            }
            if ($c['days']) {
                $ok = false;
                foreach ($c['days'] as $d) {
                    if ($d === '30+' ? ($p['days'] ?? 0) > 30 : $p['days'] === $d) {
                        $ok = true;
                        break;
                    }
                }
                if (! $ok) {
                    return false;
                }
            }
            if ($c['need'] !== null && ! $c['unlimited'] && $p['mb'] !== null) {
                $gb = $p['mb'] / 1024;
                if ($gb < $c['need'] * $cover || $gb > $c['need'] * $over) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Score every matching plan: score = wData*dataFit + wPrice*priceFit + wDays*dayFit, each in 0..1.
     *  dataFit  smallest plan that covers the need wins; overbuying is penalised; unlimited scores high only for a heavy estimate.
     *  priceFit the cheapest fitting plan is 1.0 (cheapest / this price).
     *  dayFit   validity at least the trip length, with the least overshoot.
     *
     * @param  Collection<int, array{id:int, mb:?int, days:?int, price:float}>  $matched
     * @param  array<string, mixed>  $c
     * @return Collection<int, array<string, mixed>> rows plus 'score'
     */
    public static function score(Collection $matched, array $c): Collection
    {
        if ($matched->isEmpty()) {
            return $matched;
        }
        $w = (array) config('esim_finder.weights');
        $minPrice = max(0.01, (float) $matched->min('price'));
        $heavy = $c['usage'] === 'heavy' && $c['estimate'];

        return $matched->map(function (array $p) use ($c, $w, $minPrice, $heavy): array {
            $dataFit = 0.7;
            if ($c['need'] !== null) {
                $dataFit = $p['mb'] === null ? ($heavy ? 0.95 : 0.55) : 1 / (1 + max(0.0, ($p['mb'] / 1024) / $c['need'] - 1) * 0.9);
            }
            $priceFit = $minPrice / max(0.01, $p['price']);
            $dayFit = 1.0;
            if ($c['estimate'] && $c['trip'] > 0) {
                $dayFit = $p['days'] === null ? 0.6 : ($p['days'] >= $c['trip'] ? $c['trip'] / $p['days'] : $p['days'] / $c['trip'] * 0.6);
            }

            return $p + ['score' => $w['data'] * $dataFit + $w['price'] * $priceFit + $w['days'] * $dayFit];
        })->values();
    }

    /**
     * The visitor's order. 'best' is the score; the others are plain, predictable sorts (unlimited counts as "most data" and is last
     * for $/GB since it has no GB figure).
     *
     * @param  Collection<int, array<string, mixed>>  $scored
     * @return Collection<int, array<string, mixed>>
     */
    public static function order(Collection $scored, string $sort): Collection
    {
        $cmp = match ($sort) {
            'price' => fn ($a, $b) => $a['price'] <=> $b['price'],
            'data' => fn ($a, $b) => (($b['mb'] ?? PHP_INT_MAX) <=> ($a['mb'] ?? PHP_INT_MAX)) ?: ($a['price'] <=> $b['price']),
            'days' => fn ($a, $b) => (($b['days'] ?? 0) <=> ($a['days'] ?? 0)) ?: ($a['price'] <=> $b['price']),
            'pergb' => fn ($a, $b) => (self::perGb($a) <=> self::perGb($b)) ?: ($a['price'] <=> $b['price']),
            default => fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['price'] <=> $b['price']),
        };

        return $scored->sort($cmp)->values();
    }

    /** @param array<string, mixed> $p */
    private static function perGb(array $p): float
    {
        return $p['mb'] === null ? INF : $p['price'] / max(0.001, $p['mb'] / 1024);
    }

    /**
     * Badges and the one-line "why", keyed by plan id, always computed on the BEST ordering (so "Best match" means the same thing
     * whichever sort the visitor picked).
     *
     * @param  Collection<int, array<string, mixed>>  $scored
     * @param  array<string, mixed>  $c
     * @return array<int, array{badges: list<string>, why: string}>
     */
    public static function annotate(Collection $scored, array $c): array
    {
        if ($scored->isEmpty()) {
            return [];
        }
        $best = self::order($scored, 'best');
        $cheapest = $scored->sortBy('price')->first();
        $value = $scored->filter(fn ($p) => $p['mb'] !== null)->sortBy(fn ($p) => self::perGb($p))->first();

        $out = [];
        foreach ($scored as $p) {
            $out[$p['id']] = ['badges' => [], 'why' => self::why($p, $c)];
        }
        $out[$best->first()['id']]['badges'][] = 'best';
        if ($cheapest !== null) {
            $out[$cheapest['id']]['badges'][] = 'cheapest';
        }
        if ($value !== null && $value['id'] !== $best->first()['id']) {
            $out[$value['id']]['badges'][] = 'value';
        }

        return $out;
    }

    /** @param array<string, mixed> $p @param array<string, mixed> $c */
    private static function why(array $p, array $c): string
    {
        $bits = [];
        if ($c['need'] !== null) {
            $bits[] = $p['mb'] === null ? __('esim.finder_why_unlimited', ['need' => self::gb($c['need'])]) : __('esim.finder_why_covers', ['plan' => self::gb($p['mb'] / 1024), 'need' => self::gb($c['need'])]);
        }
        if ($c['estimate'] && $c['trip'] > 0 && $p['days'] !== null) {
            $bits[] = $p['days'] >= $c['trip'] ? __('esim.finder_why_trip_ok', ['days' => $p['days'], 'trip' => $c['trip']]) : __('esim.finder_why_trip_short', ['days' => $p['days'], 'trip' => $c['trip']]);
        }
        $bits[] = $p['mb'] === null
            ? '$'.number_format($p['price'] / max(1, (int) ($p['days'] ?? 1)), 2).__('esim.finder_per_day')
            : '$'.number_format(self::perGb($p), 2).__('esim.finder_per_gb');

        return implode(' · ', $bits);
    }

    private static function gb(float $g): string
    {
        return rtrim(rtrim(number_format($g, 1), '0'), '.').' GB';
    }

    /**
     * When nothing fits: the relaxations that would bring plans back, each with how many it would show.
     *
     * @param  Collection<int, array{id:int, mb:?int, days:?int, price:float}>  $rows
     * @param  array<string, mixed>  $c
     * @return list<array{key:string, count:int, max?:float}>
     */
    public static function relaxations(Collection $rows, array $c): array
    {
        $hi = (float) config('esim_finder.price_max', 2000);
        $out = [];
        $raised = min($hi, $c['max'] * 2);
        if ($raised > $c['max'] && ($n = self::match($rows, ['max' => $raised] + $c)->count()) > 0) {
            $out[] = ['key' => 'price', 'count' => $n, 'max' => $raised];
        }
        if ($c['days'] && ($n = self::match($rows, ['days' => []] + $c)->count()) > 0) {
            $out[] = ['key' => 'days', 'count' => $n];
        }
        if (($c['need'] !== null || $c['unlimited']) && ($n = self::match($rows, ['need' => null, 'unlimited' => false] + $c)->count()) > 0) {
            $out[] = ['key' => 'need', 'count' => $n];
        }

        return $out;
    }

    /** Apply one relaxation to a criteria set. @param array<string, mixed> $c @return array<string, mixed> */
    public static function relax(array $c, string $key): array
    {
        return match ($key) {
            'price' => ['max' => min((float) config('esim_finder.price_max', 2000), $c['max'] * 2)] + $c,
            'days' => ['days' => []] + $c,
            'need' => ['need' => null, 'unlimited' => false] + $c,
            default => $c,
        };
    }

    /**
     * The lean rows for one storefront context. Only id, data, validity and the final retail price are read: never a cost column.
     * The caller's builder already carries the context (country/region/global) and the active line.
     *
     * @param  Builder<EsimPlan>  $scoped
     * @return Collection<int, array{id:int, mb:?int, days:?int, price:float}>
     */
    public static function rows(Builder $scoped): Collection
    {
        return (clone $scoped)->reorder()->whereNotNull('final_retail_usd')
            ->get(['id', 'data_mb', 'validity_days', 'final_retail_usd'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'mb' => $p->data_mb === null ? null : (int) $p->data_mb, 'days' => $p->validity_days === null ? null : (int) $p->validity_days, 'price' => (float) $p->final_retail_usd])
            ->values();
    }

    /**
     * The visitor's results as ONE ordinary query (so Livewire's own pager keeps working): the matching ids in ranked order.
     *
     * @param  Builder<EsimPlan>  $scoped
     * @param  Collection<int, array<string, mixed>>  $ordered
     * @return Builder<EsimPlan>
     */
    public static function query(Builder $scoped, Collection $ordered): Builder
    {
        $q = (clone $scoped)->reorder();
        $ids = $ordered->pluck('id')->map(fn ($i) => (int) $i)->all();
        if ($ids === []) {
            return $q->whereRaw('1 = 0');
        }
        $case = 'CASE id';
        foreach ($ids as $pos => $id) {
            $case .= ' WHEN '.$id.' THEN '.$pos;
        }

        return $q->whereIn('id', $ids)->orderByRaw($case.' END');
    }
}
