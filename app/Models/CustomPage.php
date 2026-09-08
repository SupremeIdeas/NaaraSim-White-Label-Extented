<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An admin-authored custom-HTML page (CMS). Served at /{slug} inside the
 * marketing shell. The `html` is trusted operator content (admin-only editing)
 * rendered raw; the site CSP still blocks inline scripts, so it can't become a
 * visitor-facing XSS vector.
 */
class CustomPage extends Model
{
    protected $fillable = [
        'slug', 'title', 'html', 'meta_description', 'is_published', 'in_nav', 'full_width',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'in_nav' => 'boolean',
            'full_width' => 'boolean',
        ];
    }

    /** Slugs the public resolver must never own (real routes win anyway). */
    public const RESERVED = [
        'about', 'how-it-works', 'contact', 'pricing', 'blog', 'legal', 'faq',
        'developers', 'login', 'register', 'dashboard', 'wallet', 'numbers',
        'catalogue', 'account', 'security', 'support', 'install', 'adminmaster',
        'up', 'refund-policy', 'developer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::flushNav());
        static::deleted(fn () => self::flushNav());
    }

    public static function flushNav(): void
    {
        Cache::forget('custom_pages.nav');
    }

    /**
     * Published pages flagged for the marketing nav (cached). This runs when the
     * public shell renders, so it must degrade to "no links" when the table
     * isn't reachable — pre-install (before migrations) or any DB hiccup — rather
     * than take the whole page down. Same discipline as ProviderKeys::saved().
     */
    public static function navLinks(): array
    {
        try {
            return Cache::rememberForever('custom_pages.nav', fn () => self::query()
                ->where('is_published', true)->where('in_nav', true)
                ->orderBy('title')
                ->get(['slug', 'title'])
                ->map(fn ($p) => ['slug' => $p->slug, 'title' => $p->title])
                ->all());
        } catch (\Throwable) {
            return [];
        }
    }
}
