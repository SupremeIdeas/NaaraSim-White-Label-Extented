<?php

namespace App\Support\Blog;

use App\Models\Setting;

/**
 * Admin-managed reader experience: share channels + text, the end-of-article "Keep reading" prompt, and the header blog preview.
 * One setting row, merged over defaults, resilient to a missing table so the blog never 500s.
 */
final class BlogReaderSettings
{
    public const KEY = 'blog.reader';

    public const LAYOUTS = ['grid', 'list', 'carousel'];

    public static function defaults(): array
    {
        return [
            'share_channels' => (array) config('blog_share.default_enabled', []),
            'share_text' => '',
            'share_hashtags' => '',
            'share_native' => true,
            'share_qr' => false,
            'keep_enabled' => true,
            'keep_popup' => true,            // false = inline carousel only
            'keep_count' => 6,
            'keep_scroll_pct' => 85,
            'keep_dwell_pct' => 40,
            'keep_exclude_categories' => [],
            'header_blog_layout' => 'carousel',
            'header_blog_count' => 4,
        ];
    }

    public static function all(): array
    {
        $d = self::defaults();
        try {
            $stored = Setting::getValue(self::KEY, []);
        } catch (\Throwable) {
            return $d;
        }

        return array_merge($d, is_array($stored) ? array_intersect_key($stored, $d) : []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function save(array $data): void
    {
        $d = self::defaults();
        $m = array_merge(self::all(), array_intersect_key($data, $d));
        $known = array_keys((array) config('blog_share.channels', []));
        $m['share_channels'] = array_values(array_intersect((array) $m['share_channels'], $known));
        $m['keep_count'] = max(3, min(8, (int) $m['keep_count']));
        $m['keep_scroll_pct'] = max(50, min(100, (int) $m['keep_scroll_pct']));
        $m['keep_dwell_pct'] = max(0, min(100, (int) $m['keep_dwell_pct']));
        $m['header_blog_count'] = max(1, min(8, (int) $m['header_blog_count']));
        if (! in_array($m['header_blog_layout'], self::LAYOUTS, true)) {
            $m['header_blog_layout'] = 'carousel';
        }
        foreach (['share_native', 'share_qr', 'keep_enabled', 'keep_popup'] as $b) {
            $m[$b] = (bool) $m[$b];
        }
        $m['share_text'] = mb_substr(trim((string) $m['share_text']), 0, 200);
        $m['share_hashtags'] = mb_substr(trim((string) $m['share_hashtags']), 0, 120);
        $m['keep_exclude_categories'] = array_values(array_filter(array_map('strval', (array) $m['keep_exclude_categories'])));
        Setting::setValue(self::KEY, $m, 'blog');
        RelatedPosts::flush();
    }
}
