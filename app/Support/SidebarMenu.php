<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Global glass sidebar menu (BUILD-3 §7). A slide-out, separate from the bottom
 * "More" sheet, that must always reach the legal/compliance pages an app-store
 * review needs — WITHOUT a live network path through deep app state. Everything
 * here is server-rendered into the page (and cached), so the sidebar's contents
 * are already in the DOM when it opens — no fetch required, works offline.
 *
 * Mandatory items (legal docs, account deletion, social) are always present so
 * they can't be accidentally removed; the admin CMS adds custom links, a reviews
 * URL, the links display mode, and an optional blog widget on top.
 */
class SidebarMenu
{
    private const CACHE_KEY = 'sidebar.menu.v1';

    public const LINKS_KEY = 'sidebar.custom_links';

    public const REVIEWS_KEY = 'sidebar.reviews_url';

    public const MODE_KEY = 'sidebar.display_mode';

    public const BLOG_KEY = 'sidebar.blog_widget';

    /** @return array{links: array<int, array{label:string, icon:string, url:string}>, reviews: ?string, mode: string, blog: bool} */
    public static function config(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $links = Setting::getValue(self::LINKS_KEY, []);
                $mode = (string) Setting::getValue(self::MODE_KEY, 'list');

                return [
                    'links' => is_array($links) ? array_values($links) : [],
                    'reviews' => trim((string) Setting::getValue(self::REVIEWS_KEY, '')) ?: null,
                    'mode' => in_array($mode, ['list', 'grid'], true) ? $mode : 'list',
                    'blog' => (bool) Setting::getValue(self::BLOG_KEY, false),
                ];
            } catch (\Throwable) {
                return ['links' => [], 'reviews' => null, 'mode' => 'list', 'blog' => false];
            }
        });
    }

    /** @return array<int, array{label:string, icon:string, url:string}> admin custom links */
    public static function customLinks(): array
    {
        return self::config()['links'];
    }

    public static function reviewsUrl(): ?string
    {
        return self::config()['reviews'];
    }

    /** 'list' | 'grid' — how the links section is laid out. */
    public static function displayMode(): string
    {
        return self::config()['mode'];
    }

    public static function blogWidgetEnabled(): bool
    {
        return self::config()['blog'];
    }

    /**
     * The always-present legal/compliance links (from LegalContent), each a
     * {label, slug, url}. These are never removable — app-store compliance.
     *
     * @return array<int, array{label:string, url:string}>
     */
    public static function legalLinks(): array
    {
        $out = [];
        foreach (LegalContent::slugs() as $slug) {
            $out[] = [
                'label' => LegalContent::doc($slug)['title'],
                'url' => route('legal.show', $slug),
            ];
        }

        return $out;
    }

    /** Latest published posts for the optional blog widget. */
    public static function blogPosts(int $limit = 3): Collection
    {
        if (! self::blogWidgetEnabled()) {
            return collect();
        }
        try {
            return Post::published()->latest('published_at')->take($limit)->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
