<?php

namespace App\Support;

/**
 * Per-platform handle-URL format checks (BUILD-9 §5.1.2). A FORMAT check (is the
 * URL in a recognized, followable shape) — not a live existence check; whether a
 * real user's follow can be verified is the separate "verified vs self-confirmed"
 * distinction (BUILD-6 §C). Patterns are exposed to the browser too, for the live
 * green/red indicator as the business types.
 */
class BrandHandleFormat
{
    /** @var array<string, string> platform => JS-compatible regex source (no delimiters) */
    public const PATTERNS = [
        'instagram' => '^https?://(www\\.)?instagram\\.com/[A-Za-z0-9._]{1,40}/?$',
        'x' => '^https?://(www\\.)?(x|twitter)\\.com/[A-Za-z0-9_]{1,30}/?$',
        'youtube' => '^https?://(www\\.)?youtube\\.com/(@[A-Za-z0-9._-]{1,40}|channel/[A-Za-z0-9_-]{10,40})/?$',
        'tiktok' => '^https?://(www\\.)?tiktok\\.com/@[A-Za-z0-9._]{1,40}/?$',
        'facebook' => '^https?://(www\\.)?facebook\\.com/[A-Za-z0-9._-]{1,60}/?$',
        'linkedin' => '^https?://(www\\.)?linkedin\\.com/(company|in)/[A-Za-z0-9._-]{1,80}/?$',
        'threads' => '^https?://(www\\.)?threads\\.(net|com)/@?[A-Za-z0-9._]{1,40}/?$',
        'snapchat' => '^https?://(www\\.)?snapchat\\.com/add/[A-Za-z0-9._-]{1,40}/?$',
    ];

    public static function matches(string $platform, string $url): bool
    {
        $pattern = self::PATTERNS[$platform] ?? null;
        if ($pattern === null) {
            // Unknown platform → accept any http(s) URL.
            return (bool) preg_match('#^https?://.+#i', trim($url));
        }

        return (bool) preg_match('#'.$pattern.'#i', trim($url));
    }

    /** @return array<string, string> platform => human hint */
    public static function hint(string $platform): string
    {
        return match ($platform) {
            'instagram' => 'e.g. instagram.com/yourname',
            'x' => 'e.g. x.com/yourname',
            'youtube' => 'e.g. youtube.com/@yourchannel',
            'tiktok' => 'e.g. tiktok.com/@yourname',
            'facebook' => 'e.g. facebook.com/yourpage',
            'linkedin' => 'e.g. linkedin.com/company/yourbrand',
            'threads' => 'e.g. threads.net/@yourname',
            'snapchat' => 'e.g. snapchat.com/add/yourname',
            default => 'Paste the full profile URL',
        };
    }
}
