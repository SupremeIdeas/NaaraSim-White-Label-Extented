<?php

namespace App\Support;

use App\Models\Banner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Cached banner lookup per placement zone (Module 31). Flushed whenever a
 * banner is saved or deleted (hooked in AppServiceProvider) so the admin sees
 * changes immediately without hitting the DB on every page view.
 */
class Banners
{
    private const CACHE_KEY = 'banners.live.v1';

    /** @return \Illuminate\Support\Collection<int, Banner> */
    public static function for(string $placement)
    {
        // Short TTL (not forever): schedule windows (starts_at/ends_at) must
        // take effect on time even without an admin save to flush the cache.
        $all = Cache::remember(self::CACHE_KEY, 300, function () {
            if (! Schema::hasTable('banners')) {
                return collect();
            }

            return Banner::query()->live()
                ->with('coupon')
                ->orderBy('sort_order')->orderBy('id')
                ->get();
        });

        return $all->where('placement', $placement)->values();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
