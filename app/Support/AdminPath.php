<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * NAARA-PROMPT-22 §2 — the admin panel's URL prefix, resolved request-time
 * from the database (super_admin editable from Admin -> Security) with
 * config('admin.path') as the fallback for an install that never touched it.
 * Setting's own cache layer busts on save (Setting::setValue()), so a change
 * takes effect on the very next request without a route:cache clear — the
 * whole point of moving from a boot-time literal to this resolver.
 */
class AdminPath
{
    public const SETTING_KEY = 'admin_path';

    /** Same bound the route constraint enforces — a malformed stored value can't happen. */
    public const PATTERN = '[A-Za-z0-9\-_]{3,64}';

    /**
     * Database value wins; environment value is the fallback if unset. Never
     * throws — a missing settings table on a fresh boot (pre-migrate) or a
     * DB hiccup must never break every request, since this is read on every
     * page load to seed route() defaults (see AppServiceProvider::boot()).
     */
    public static function current(): string
    {
        try {
            return (string) Setting::getValue(self::SETTING_KEY, config('admin.path'));
        } catch (\Throwable) {
            return (string) config('admin.path');
        }
    }

    public static function isValidFormat(string $candidate): bool
    {
        return (bool) preg_match('/^'.self::PATTERN.'$/', $candidate);
    }

    /**
     * A real reserved-word check against the actual route table — not a
     * guess at which words might collide. Excludes the admin gateway's own
     * routes (their first URI segment is the literal placeholder
     * "{adminGateway}", which never matches a real candidate) so this never
     * self-collides. Also checks top-level `public/` directory names, since
     * on a real web server those are served before any Laravel route runs
     * at all — a path colliding with one would be silently shadowed.
     */
    public static function collidesWithReserved(string $candidate): bool
    {
        $needle = strtolower($candidate);

        foreach (Route::getRoutes() as $route) {
            $first = strtok($route->uri(), '/');
            if ($first !== false && ! str_contains($first, '{') && strtolower($first) === $needle) {
                return true;
            }
        }

        foreach (self::publicTopLevelDirectories() as $dir) {
            if (strtolower($dir) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function publicTopLevelDirectories(): array
    {
        try {
            return array_map(
                fn (string $path) => basename($path),
                File::directories(public_path()),
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
