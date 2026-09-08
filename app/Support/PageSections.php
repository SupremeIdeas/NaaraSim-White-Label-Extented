<?php

namespace App\Support;

use App\Models\PageSection;
use App\Models\PageSectionVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Read/render seam for the Section Builder. The public site asks for a page's
 * LIVE sections (the last published snapshot); the admin builder works on the
 * DRAFT rows. Live snapshots are cached and busted on publish, so serving a
 * built page costs one cache hit, not a query per request.
 */
class PageSections
{
    /**
     * The live (published) sections for a page, active-only, ordered. Empty when
     * the page has never been published — callers treat empty as "fall back to
     * the existing hardcoded view", so nothing breaks until an admin opts in.
     *
     * @return array<int, array{type:string, config:array}>
     */
    public static function live(string $page): array
    {
        // Public pages render this on EVERY request — a DB hiccup or a
        // not-yet-migrated table must never 500 the page. Catch outside the cache
        // so a transient error returns "no built version" (→ hardcoded content
        // shows) without poisoning the cache with an empty result.
        try {
            return Cache::rememberForever(self::cacheKey($page), function () use ($page) {
                $version = PageSectionVersion::query()
                    ->where('page_key', $page)
                    ->where('is_live', true)
                    ->latest('id')
                    ->first();

                if (! $version) {
                    return [];
                }

                return collect($version->snapshot)
                    ->filter(fn ($s) => ($s['is_active'] ?? true) && SectionLibrary::has($s['type'] ?? ''))
                    ->sortBy('sort_order')
                    ->map(fn ($s) => ['type' => $s['type'], 'config' => $s['config'] ?? []])
                    ->values()
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    public static function hasLive(string $page): bool
    {
        return self::live($page) !== [];
    }

    /**
     * The draft rows for a page as a render-ready array (used by the admin
     * preview so it shows exactly what the public renderer will show).
     *
     * @return array<int, array{type:string, config:array}>
     */
    public static function draft(string $page): array
    {
        return PageSection::query()
            ->where('page_key', $page)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($s) => SectionLibrary::has($s->type))
            ->map(fn ($s) => ['type' => $s->type, 'config' => (array) $s->config])
            ->values()
            ->all();
    }

    public static function flush(?string $page = null): void
    {
        if ($page) {
            Cache::forget(self::cacheKey($page));

            return;
        }

        foreach (PageSectionVersion::query()->distinct()->pluck('page_key') as $key) {
            Cache::forget(self::cacheKey($key));
        }
    }

    /**
     * Resolve an admin-entered CTA/target into a real URL. Accepts: an absolute
     * URL, a same-page anchor (#id), a named route ('route:dashboard' or a bare
     * known route name), or a relative path. Empty stays empty so the caller can
     * hide the button.
     */
    public static function target(?string $target): string
    {
        $target = trim((string) $target);
        if ($target === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $target) || str_starts_with($target, '#')
            || str_starts_with($target, 'mailto:') || str_starts_with($target, 'tel:')) {
            return $target;
        }

        $name = str_starts_with($target, 'route:') ? substr($target, 6) : $target;
        if (Route::has($name)) {
            try {
                return route($name);
            } catch (\Throwable) {
                // Route needs params we don't have — fall through to path handling.
            }
        }

        return str_starts_with($target, '/') ? $target : '/'.ltrim($target, '/');
    }

    private static function cacheKey(string $page): string
    {
        return 'page.sections.live.'.$page;
    }
}
