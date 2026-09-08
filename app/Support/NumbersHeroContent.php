<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Numbers landing hero content (Numbers V6 §0). Admin-managed title,
 * description, and up to FOUR ordered images for the interchanging-reveal hero.
 * Setting-backed + cached; falls back to the shipped seed images so the hero
 * always looks complete before the admin uploads their own. Mirrors
 * EsimHeroContent so both heroes share one pattern.
 */
class NumbersHeroContent
{
    private const CACHE = 'numbers.hero.v1';

    public const MAX_IMAGES = 4;

    public const TITLE_KEY = 'numbers.hero.title';

    public const DESC_KEY = 'numbers.hero.description';

    public const IMAGES_KEY = 'numbers.hero.images';

    /** Shipped defaults (extracted seed assets under /public/img/numbers). */
    private const DEFAULT_IMAGES = [
        '/img/numbers/hero-1.webp',
        '/img/numbers/hero-2.webp',
        '/img/numbers/hero-3.webp',
        '/img/numbers/hero-4.webp',
    ];

    private const DEFAULT_TITLE = 'A number for everything.';

    private const DEFAULT_DESC = 'Verification, rentals and a permanent line — voice & SMS.';

    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            try {
                $images = Setting::getValue(self::IMAGES_KEY);
                $images = is_array($images) && $images !== [] ? array_values($images) : self::DEFAULT_IMAGES;

                return [
                    'title' => Setting::getValue(self::TITLE_KEY) ?: self::DEFAULT_TITLE,
                    'description' => Setting::getValue(self::DESC_KEY) ?: self::DEFAULT_DESC,
                    'images' => array_slice($images, 0, self::MAX_IMAGES),
                ];
            } catch (\Throwable) {
                return [
                    'title' => self::DEFAULT_TITLE,
                    'description' => self::DEFAULT_DESC,
                    'images' => self::DEFAULT_IMAGES,
                ];
            }
        });
    }

    public static function title(): string
    {
        return self::current()['title'];
    }

    public static function description(): string
    {
        return self::current()['description'];
    }

    /** @return list<string> ordered image URLs (1–4). */
    public static function images(): array
    {
        return self::current()['images'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }

    public static function isHeroKey(string $key): bool
    {
        return str_starts_with($key, 'numbers.hero.');
    }
}
