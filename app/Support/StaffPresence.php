<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight staff presence (Module 25) — who's online to take a ticket.
 * A short-lived cache key per staff id is refreshed on each admin request
 * (heartbeat from EnsureAdmin); "online" = seen within the TTL. Cache-based so
 * it works on any driver and needs no websockets.
 */
class StaffPresence
{
    private const TTL = 120; // seconds

    private static function key(int $id): string
    {
        return "staff-online:{$id}";
    }

    public static function heartbeat(User $user): void
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'staff'])) {
            Cache::put(self::key($user->id), now()->timestamp, self::TTL);
        }
    }

    public static function isOnline(int $userId): bool
    {
        return Cache::has(self::key($userId));
    }

    /**
     * Online staff who can work tickets (hold the tickets.manage permission or
     * are admin/super_admin).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function availableAgents(): \Illuminate\Support\Collection
    {
        return User::role(['super_admin', 'admin', 'staff'])
            ->get()
            ->filter(fn (User $u) => self::isOnline($u->id)
                && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('tickets.manage')))
            ->values();
    }
}
