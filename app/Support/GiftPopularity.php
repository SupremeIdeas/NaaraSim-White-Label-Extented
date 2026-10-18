<?php

namespace App\Support;

use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use Illuminate\Support\Facades\Cache;

/**
 * "Popular first" for the gift store. The order is: what people on THIS install actually buy (last 180 days), then a curated list of the
 * brands most bought worldwide (games, streaming, marketplaces, food, travel). A brand matches by its base key, so steam-brazil and steam-chile
 * rank with steam. Everything else follows alphabetically; an admin's "featured" flag still beats all of it.
 */
final class GiftPopularity
{
    /** Most bought gift-card brands worldwide, most popular first (matches the catalogue's brand_key, country variants included). */
    public const WORLD = [
        'amazon', 'google-play*', 'apple', 'itunes*', 'steam*', 'netflix*', 'playstation-store', 'xbox', 'roblox*', 'razer-gold*', 'free-fire*', 'pubg*', 'uber',
        'spotify', 'nintendo', 'walmart', 'starbucks', 'airbnb', 'nike*', 'disney-plus', 'twitch', 'fortnite*', 'minecraft*', 'valorant*', 'league-of-legends*', 'mobile-legends*',
        'adidas*', 'sephora', 'ikea', 'target', 'dominos', 'mcdonalds', 'kfc', 'doordash', 'deliveroo', 'shein', 'zara', 'candy-crush', 'hulu-plus', 'uber-eats', 'grab-food',
    ];  // a trailing * also matches country-suffixed variants (steam-brazil); the others match exactly (amazon-fresh is a different product)


    /** @return list<string> brand base keys, best first */
    public static function ranked(): array
    {
        $bought = Cache::remember('gift.popularity.bought', 3600, function () {
            try {
                return GiftCardOrder::query()->where('gift_card_orders.created_at', '>=', now()->subDays(180))
                    ->join('gift_card_products as p', fn ($j) => $j->on('p.id', '=', 'gift_card_orders.gift_card_product_id'))
                    ->selectRaw('p.brand_key, COUNT(*) as n')->groupBy('p.brand_key')->orderByDesc('n')->limit(12)->pluck('brand_key')->all();
            } catch (\Throwable) {
                return [];
            }
        });

        return array_values(array_unique(array_merge($bought, self::WORLD)));
    }

    /** SQL rank (1 = most popular, 999 = unranked) as [expression, bindings]; no `||` (MySQL reads it as OR). */
    public static function rankSql(): array
    {
        $parts = [];
        $bind = [];
        foreach (self::ranked() as $i => $key) {
            $prefix = str_ends_with($key, '*');
            $base = rtrim($key, '*');
            $parts[] = $prefix ? 'WHEN gift_card_products.brand_key = ? OR gift_card_products.brand_key LIKE ? THEN '.($i + 1) : 'WHEN gift_card_products.brand_key = ? THEN '.($i + 1);
            array_push($bind, $base);
            if ($prefix) {
                $bind[] = $base.'-%';
            }
        }

        return ['CASE '.implode(' ', $parts).' ELSE 999 END', $bind];
    }

    /**
     * Popular-first ordering for a gift-card query. Tiers: the admin's featured cards; each popular brand's lead card (best first); every other brand's
     * lead card; then country variants (popular brands first). So the first screen is one card of each most-bought brand, not 69 Amazon countries.
     */
    public static function order(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        self::ensureLeads();
        [$rank, $bind] = self::rankSql();

        return $q->orderByDesc('featured')
            ->orderByRaw("CASE WHEN gift_card_products.is_lead = 1 AND ($rank) < 999 THEN 0 WHEN gift_card_products.is_lead = 1 THEN 1 ELSE 2 END", $bind)
            ->orderByRaw($rank, $bind)->orderByRaw("CASE WHEN country = 'US' THEN 0 ELSE 1 END")->orderBy('brand_name');
    }

    /** Flag one lead card per brand (the US card, else the lowest id). Idempotent; run after every sync, and lazily on first use. */
    public static function markLeads(): int
    {
        GiftCardProduct::query()->where('is_lead', true)->update(['is_lead' => false]);
        $seen = [];
        $ids = [];
        GiftCardProduct::query()->select(['id', 'brand_key', 'country'])->where('is_primary', true)->orderBy('brand_key')
            ->orderByRaw("CASE WHEN country = 'US' THEN 0 ELSE 1 END")->orderBy('id')->chunk(2000, function ($rows) use (&$seen, &$ids) {
                foreach ($rows as $r) {
                    if (! isset($seen[$r->brand_key])) {
                        $seen[$r->brand_key] = true;
                        $ids[] = $r->id;
                    }
                }
            });
        foreach (array_chunk($ids, 1000) as $chunk) {
            GiftCardProduct::query()->whereIn('id', $chunk)->update(['is_lead' => true]);
        }
        Cache::forget('gift.popularity.leads');

        return count($ids);
    }

    private static function ensureLeads(): void
    {
        try {
            $has = Cache::remember('gift.popularity.leads', 300, fn () => GiftCardProduct::query()->where('is_lead', true)->exists());
            if (! $has && GiftCardProduct::query()->exists()) {
                self::markLeads();
                Cache::put('gift.popularity.leads', true, 300);
            }
        } catch (\Throwable) {
        }
    }

    /** The brand_keys of the ranked brands that exist in the live store. @return list<string> */
    public static function liveKeys(int $limit = 40): array
    {
        $live = GiftCardProduct::query()->storefront()->distinct()->pluck('brand_key')->all();
        $out = [];
        foreach (self::ranked() as $base) {
            $base = rtrim($base, '*');
            foreach ($live as $k) {
                if ($k === $base) {
                    $out[$k] = true;
                }
            }
        }

        return array_slice(array_keys($out), 0, $limit);
    }
}
