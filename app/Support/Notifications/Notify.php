<?php

namespace App\Support\Notifications;

use App\Models\User;
use App\Notifications\EventNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Raise a named event for a person (or every admin). Resolves the matrix row, applies the person's preferences and quiet hours, delivers each
 * (person, event, dedupe key) once, and queues the delivery. It NEVER throws into the caller: a notification problem must not break a payout,
 * a refund or a sign-in; failures are reported and the money path carries on.
 *
 *   Notify::event($user, 'payout.frozen', [], dedupe: 'freeze-'.$freezeId);
 *   Notify::admins('admin.stuck_payout', ['id' => 9, 'status' => 'approved', 'since' => '2 hours ago'], dedupe: 'payout-9');
 *
 * @return list<string> the channels used (empty = nothing sent: unknown event, deduped, or every channel switched off)
 */
final class Notify
{
    private const VIA = ['in_app' => 'database', 'toast' => 'database', 'push' => 'webpush', 'email' => 'mail'];

    public static function event(User $user, string $event, array $vars = [], ?string $dedupe = null): array
    {
        try {
            $plan = self::plan($user, $event, $vars, $dedupe);
            if ($plan === null) {
                return [];
            }
            $user->notify(new EventNotification($event, $plan['payload'], $plan['via']));

            return $plan['channels'];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Operators: every super admin and admin. Preferences do not apply to operator alerts (critical), quiet hours still hold push. */
    public static function admins(string $event, array $vars = [], ?string $dedupe = null): int
    {
        $sent = 0;
        try {
            foreach (User::role(['super_admin', 'admin'])->get() as $admin) {
                $sent += self::event($admin, $event, $vars, $dedupe) !== [] ? 1 : 0;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $sent;
    }

    /** @return null|array{payload: array, via: list<string>, channels: list<string>} */
    public static function plan(User $user, string $event, array $vars = [], ?string $dedupe = null): ?array
    {
        $row = ((array) config('notification_events.events', []))[$event] ?? null;   // not config("...events.$event"): event keys contain dots
        if (! is_array($row)) {
            return null;
        }
        $category = (string) $row['category'];

        $channels = [];
        foreach ((array) $row['channels'] as $ch) {
            if (! NotificationPrefs::wants($user, $category, $ch)) {
                continue;
            }
            if ($ch === 'push' && ! NotificationPrefs::isCritical($category) && NotificationPrefs::inQuietHours($user)) {
                continue;   // quiet hours hold push only; the in-app copy is already waiting
            }
            $channels[] = $ch;
        }
        if ($channels === []) {
            return null;
        }
        // Mark first, deliver second: a retried or duplicated trigger can never send twice.
        if (($row['dedupe'] ?? false) && $dedupe !== null && ! self::claim($user->id, $event, $dedupe)) {
            return null;
        }

        $fill = fn (string $s) => preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $s);
        $route = $row['route'] ?? null;
        $payload = [
            'category' => $category === 'admin' ? 'system' : ($category === 'security' ? 'security' : ($category === 'billing' ? 'wallet' : 'system')),
            'icon' => (string) ($row['icon'] ?? 'bell'),
            'title' => trim($fill((string) $row['title'])),
            'body' => trim($fill((string) $row['body'])),
            'action_url' => $route && Route::has($route) ? route($route) : null,
            'action_label' => $route ? 'Open' : null,
            'event' => $event,
            'toast' => in_array('toast', $channels, true),
        ];

        return ['payload' => $payload, 'via' => array_values(array_unique(array_map(fn ($c) => self::VIA[$c], $channels))), 'channels' => $channels];
    }

    private static function claim(int $userId, string $event, string $key): bool
    {
        if (! Schema::hasTable('notification_event_log')) {
            return true;
        }

        return DB::table('notification_event_log')->insertOrIgnore(['user_id' => $userId, 'event' => $event, 'dedupe_key' => mb_substr($key, 0, 120), 'created_at' => now()]) === 1;
    }
}
