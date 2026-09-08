<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-managed blog hero (Blog overhaul §2): up to 4 interchanging images plus
 * a title + subtitle. Setting-backed, resilient to a missing table (mirrors the
 * other *Settings supports) so the blog never 500s.
 */
class BlogSettings
{
    public const KEY = 'blog.hero';

    public static function defaults(): array
    {
        return [
            'title' => 'Travel smarter, stay connected',
            'subtitle' => 'Guides, tips and updates on eSIMs, numbers and travelling without roaming surprises.',
            'images' => [],
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

    /** @return array<int, string> up to 4 image URLs */
    public static function images(): array
    {
        return array_values(array_filter((array) self::get('images', [])));
    }

    public static function save(array $data): void
    {
        $merged = array_merge(self::all(), array_intersect_key($data, self::defaults()));
        $merged['images'] = array_slice(array_values(array_filter((array) ($merged['images'] ?? []))), 0, 4);
        Setting::setValue(self::KEY, $merged, 'blog');
    }
}
