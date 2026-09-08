<?php

namespace App\Support;

/**
 * The social platforms a follow-to-earn handle can belong to (BUILD-6 §C). An
 * extensible slug => label list — new platforms are added here, not via a schema
 * change. Slugs match the service-icon sprite so <x-service-icon> renders them.
 */
class SocialPlatforms
{
    /** @var array<string, string> slug => label */
    public const PLATFORMS = [
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'x' => 'X (Twitter)',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'threads' => 'Threads',
        'snapchat' => 'Snapchat',
    ];

    /** Platforms with a real follow/subscription check API (see the decision doc). */
    public const API_CAPABLE = ['x', 'youtube'];

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::PLATFORMS;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::PLATFORMS);
    }

    public static function isValid(string $slug): bool
    {
        return isset(self::PLATFORMS[$slug]);
    }

    public static function label(string $slug): string
    {
        return self::PLATFORMS[$slug] ?? ucfirst($slug);
    }
}
