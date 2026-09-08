<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * eSIM storefront hero content (esim_upgrade Part 2). Admin-managed title,
 * description, and up to FOUR ordered images for the interchanging-reveal hero.
 * Setting-backed + cached; falls back to the shipped seed images so the hero
 * always looks complete before the admin uploads their own.
 */
class EsimHeroContent
{
    private const CACHE = 'esim.hero.v1';

    public const MAX_IMAGES = 4;

    public const TITLE_KEY = 'esim.hero.title';

    public const DESC_KEY = 'esim.hero.description';

    public const IMAGES_KEY = 'esim.hero.images';

    // The catalogue section heading + subheading shown below the hero banner
    // (admin-editable). Kept under the esim.hero.* prefix so the same cache
    // auto-flush hook applies.
    public const SECTION_TITLE_KEY = 'esim.hero.section_title';

    public const SECTION_SUBTITLE_KEY = 'esim.hero.section_subtitle';

    public const DEFAULT_SECTION_TITLE = 'eSIM Plans';

    public const DEFAULT_SECTION_SUBTITLE = '190+ countries. Stay connected. No borders. No swaps.';

    /** Shipped defaults (extracted seed assets under /public/img/esim). */
    private const DEFAULT_IMAGES = [
        '/img/esim/hero-1.webp',
        '/img/esim/hero-2.webp',
        '/img/esim/hero-3.webp',
        '/img/esim/hero-4.webp',
    ];

    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            try {
                $images = Setting::getValue(self::IMAGES_KEY);
                $images = is_array($images) && $images !== [] ? array_values($images) : self::DEFAULT_IMAGES;

                return [
                    'title' => Setting::getValue(self::TITLE_KEY) ?: 'Data that follows you. No borders. No swaps.',
                    'description' => Setting::getValue(self::DESC_KEY) ?: 'Instant eSIM data for 190+ countries — installed in minutes, right from your phone.',
                    'section_title' => Setting::getValue(self::SECTION_TITLE_KEY) ?: self::DEFAULT_SECTION_TITLE,
                    'section_subtitle' => Setting::getValue(self::SECTION_SUBTITLE_KEY) ?: self::DEFAULT_SECTION_SUBTITLE,
                    'images' => array_slice($images, 0, self::MAX_IMAGES),
                ];
            } catch (\Throwable) {
                return [
                    'title' => 'Data that follows you. No borders. No swaps.',
                    'description' => 'Instant eSIM data for 190+ countries — installed in minutes, right from your phone.',
                    'section_title' => self::DEFAULT_SECTION_TITLE,
                    'section_subtitle' => self::DEFAULT_SECTION_SUBTITLE,
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

    /** The catalogue section heading (admin-editable; defaults to "eSIM Plans"). */
    public static function sectionTitle(): string
    {
        // Null-coalesce guards a warm cache from before these keys existed.
        return self::current()['section_title'] ?? self::DEFAULT_SECTION_TITLE;
    }

    /** The catalogue section subheading (admin-editable). */
    public static function sectionSubtitle(): string
    {
        return self::current()['section_subtitle'] ?? self::DEFAULT_SECTION_SUBTITLE;
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
        return str_starts_with($key, 'esim.hero.');
    }
}
