<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Catalogue-sync observability (esim_upgrade Part 1). Records the last sync
 * outcome per eSIM provider — when, success/fail, plan count, and the error on
 * failure — so a broken provider never looks identical to "up to date" in the
 * admin. Stored as one Setting row; cached.
 */
class SyncStatus
{
    private const KEY = 'esim.sync_status';

    private const CACHE = 'esim.sync_status.v1';

    /**
     * Record the outcome of one provider sync. `skipped` (Tier 4 #10 Phase
     * A1) marks a provider with no API key configured — a legitimate,
     * NEUTRAL state shown distinctly from both a successful sync and a
     * genuine failure, never lumped in with either.
     */
    public static function record(string $provider, bool $ok, ?int $count = null, ?string $error = null, bool $skipped = false): void
    {
        try {
            $all = self::all();
            $all[$provider] = [
                'ok' => $ok,
                'count' => $count,
                'error' => $error ? mb_substr($error, 0, 300) : null,
                'skipped' => $skipped,
                'at' => now()->toIso8601String(),
            ];
            Setting::setValue(self::KEY, $all, 'esim');
            Cache::forget(self::CACHE);
        } catch (\Throwable) {
            // observability must never break the sync itself
        }
    }

    /** @return array<string, array{ok: bool, count: ?int, error: ?string, skipped: bool, at: string}> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            try {
                $v = Setting::getValue(self::KEY);

                return is_array($v) ? $v : [];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** @return array{ok: bool, count: ?int, error: ?string, skipped: bool, at: string}|null */
    public static function for(string $provider): ?array
    {
        return self::all()[$provider] ?? null;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }
}
