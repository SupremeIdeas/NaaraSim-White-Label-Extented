<?php

namespace App\Support;

use App\Models\HomeVideo;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Homepage video section (BUILD-3 §8). Wraps the admin-editable section heading
 * and the active, ordered list of video entries. Cached; self-hides when there
 * are no playable entries so the homepage never shows an empty section.
 */
class HomeVideos
{
    private const CACHE_KEY = 'home.videos.v1';

    public const HEADING_KEY = 'home.videos.heading';

    public const SUBHEADING_KEY = 'home.videos.subheading';

    public const DEFAULT_HEADING = 'See NaaraSim in action';

    public const DEFAULT_SUBHEADING = 'Short guides to getting connected — data, numbers, and your wallet.';

    /** @return Collection<int, HomeVideo> */
    public static function active(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return HomeVideo::where('is_active', true)
                    ->orderBy('sort_order')->orderBy('id')
                    ->get()
                    ->filter->isPlayable()
                    ->values();
            } catch (\Throwable) {
                return collect();
            }
        });
    }

    public static function isVisible(): bool
    {
        return self::active()->isNotEmpty();
    }

    public static function heading(): string
    {
        $v = trim((string) Setting::getValue(self::HEADING_KEY, ''));

        return $v !== '' ? $v : self::DEFAULT_HEADING;
    }

    public static function subheading(): string
    {
        $v = trim((string) Setting::getValue(self::SUBHEADING_KEY, ''));

        return $v !== '' ? $v : self::DEFAULT_SUBHEADING;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
