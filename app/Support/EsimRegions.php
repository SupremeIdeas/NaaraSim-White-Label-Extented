<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The canonical region taxonomy for the eSIM navigation (BUILD-8 §2/§3).
 *
 * Providers each name regions slightly differently ("Europe", "EU",
 * "North America", "world", "global"). This is the ONE place those real,
 * provider-supplied names are normalised to a stable Naara slug, so:
 *   - the sync stores a consistent region_slug (from a real signal only —
 *     never guessed, per §2.1), and
 *   - the Regional/Global tabs (§3) and esim_region_images key on the same slug.
 *
 * The slug set intentionally mirrors the admin-seeded region banners
 * (Africa, Asia, Caribbean, Europe, Latin America, MENA, North America,
 * Oceania) plus the single global 'world' tile.
 */
class EsimRegions
{
    public const WORLD = 'world';

    /** slug => display label. */
    public const LABELS = [
        'africa' => 'Africa',
        'asia' => 'Asia',
        'caribbean' => 'Caribbean',
        'europe' => 'Europe',
        'latin-america' => 'Latin America',
        'mena' => 'Middle East & North Africa',
        'north-america' => 'North America',
        'oceania' => 'Oceania',
        self::WORLD => 'Global',
    ];

    /**
     * Common provider aliases → canonical slug. Only real, observed region names
     * are mapped; anything unrecognised returns null so the sync leaves
     * region_slug empty rather than inventing a grouping.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'africa' => 'africa',
        'asia' => 'asia', 'asia pacific' => 'asia', 'apac' => 'asia', 'asia-pacific' => 'asia',
        'caribbean' => 'caribbean', 'caribbean islands' => 'caribbean',
        'europe' => 'europe', 'eu' => 'europe', 'european union' => 'europe',
        'latin america' => 'latin-america', 'latam' => 'latin-america',
        'south america' => 'latin-america', 'central america' => 'latin-america',
        'mena' => 'mena', 'middle east' => 'mena',
        'middle east & north africa' => 'mena', 'middle east and north africa' => 'mena',
        'gulf' => 'mena',
        'north america' => 'north-america', 'usa & canada' => 'north-america',
        'oceania' => 'oceania', 'pacific' => 'oceania', 'australia & new zealand' => 'oceania',
        'world' => self::WORLD, 'global' => self::WORLD, 'worldwide' => self::WORLD,
        'discover global' => self::WORLD, 'discover global (world map)' => self::WORLD,
    ];

    /** @return array<string, string> slug => label */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /** @return list<string> canonical slugs (world last). */
    public static function slugs(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $slug): string
    {
        $slug = (string) $slug;

        return self::LABELS[$slug] ?? Str::of($slug)->replace('-', ' ')->title()->toString();
    }

    public static function isGlobal(?string $slug): bool
    {
        return $slug === self::WORLD;
    }

    /**
     * Normalise a raw, provider-supplied region name/slug to a canonical Naara
     * slug, or null when it isn't a recognisable region (so the sync stays honest
     * and leaves region_slug empty rather than guessing).
     */
    public static function normalize(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $key = mb_strtolower($raw);
        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // Try the slugged form (e.g. "North-America" / "north_america").
        $slug = Str::slug($raw);
        if (isset(self::LABELS[$slug])) {
            return $slug;
        }
        if (isset(self::ALIASES[str_replace('-', ' ', $slug)])) {
            return self::ALIASES[str_replace('-', ' ', $slug)];
        }

        return null;
    }
}
