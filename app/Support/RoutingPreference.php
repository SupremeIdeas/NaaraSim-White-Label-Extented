<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * NAARA-BUILD-17 §1.4 — the admin "prefer Provider X for the next N hours"
 * override. This is the "admin-set manual priority" tiebreaker BUILD-15 §4.1
 * reserved a slot for — one mechanism, not a second override system. Stored with
 * a TTL so it self-expires (a preference is inherently temporary), keyed by
 * stack, and consulted by CandidateOrdering. It only floats a preferred provider
 * to the front of the ORDER — it never resurrects an open circuit or skips the
 * live call.
 */
class RoutingPreference
{
    private static function key(string $stack): string
    {
        return 'routing.prefer.'.$stack;
    }

    /** Prefer $providerKey for $stack for the next $hours. */
    public static function prefer(string $stack, string $providerKey, int $hours): void
    {
        $hours = max(1, min(168, $hours)); // 1h..1 week
        Cache::put(self::key($stack), ['provider' => $providerKey, 'until' => now()->addHours($hours)->toIso8601String()], now()->addHours($hours));
    }

    /** The currently-preferred provider for $stack, or null. */
    public static function preferred(string $stack): ?string
    {
        $v = Cache::get(self::key($stack));

        return is_array($v) ? ($v['provider'] ?? null) : null;
    }

    /** Full record (provider + until) for display, or null. */
    public static function current(string $stack): ?array
    {
        $v = Cache::get(self::key($stack));

        return is_array($v) ? $v : null;
    }

    public static function clear(string $stack): void
    {
        Cache::forget(self::key($stack));
    }
}
