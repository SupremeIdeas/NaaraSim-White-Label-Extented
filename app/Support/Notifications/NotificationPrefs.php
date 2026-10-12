<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A person's notification choices: per category and channel (in_app / push / email), plus quiet hours in their own timezone.
 * Absent = the catalog default (config/notification_catalog.php `default_on`). Critical categories (security, billing, admin) can pick
 * channels but cannot be silenced: the in-app copy always lands. Quiet hours hold back push only, never in-app or a critical email.
 */
final class NotificationPrefs
{
    public const CHANNELS = ['in_app', 'push', 'email'];

    /** @return array{channels: array<string, array<string,bool>>, quiet_start: ?string, quiet_end: ?string} */
    public static function for(User $u): array
    {
        $row = null;
        try {
            if (Schema::hasTable('notification_preferences')) {
                $row = DB::table('notification_preferences')->where('user_id', $u->id)->first();
            }
        } catch (\Throwable) {
        }

        return [
            'channels' => $row && $row->channels ? (array) json_decode($row->channels, true) : [],
            'quiet_start' => $row->quiet_start ?? null,
            'quiet_end' => $row->quiet_end ?? null,
        ];
    }

    public static function isCritical(string $category): bool
    {
        return (bool) config("notification_catalog.categories.$category.critical", false);
    }

    /** Is this channel wanted for this category? (toast follows in_app; the catalog default applies when unset.) */
    public static function wants(User $u, string $category, string $channel): bool
    {
        $channel = $channel === 'toast' ? 'in_app' : $channel;
        if ($channel === 'in_app' && self::isCritical($category)) {
            return true;
        }
        $saved = self::for($u)['channels'][$category][$channel] ?? null;
        if ($saved !== null) {
            return (bool) $saved;
        }

        return (bool) config("notification_catalog.categories.$category.default_on", true);
    }

    public static function inQuietHours(User $u, ?\DateTimeInterface $now = null): bool
    {
        $p = self::for($u);
        if (! $p['quiet_start'] || ! $p['quiet_end'] || $p['quiet_start'] === $p['quiet_end']) {
            return false;
        }
        try {
            $tz = new \DateTimeZone($u->timezone ?: 'UTC');
        } catch (\Throwable) {
            $tz = new \DateTimeZone('UTC');
        }
        $local = \Carbon\Carbon::instance($now ?? now())->setTimezone($tz)->format('H:i');
        [$s, $e] = [$p['quiet_start'], $p['quiet_end']];

        return $s < $e ? ($local >= $s && $local < $e) : ($local >= $s || $local < $e);   // a window may cross midnight
    }

    public static function save(User $u, array $channels, ?string $start, ?string $end): void
    {
        $clean = [];
        foreach ((array) config('notification_catalog.categories', []) as $cat => $meta) {
            if (in_array($cat, ['admin', 'white_label'], true)) {
                continue;
            }
            foreach (self::CHANNELS as $ch) {
                if (isset($channels[$cat][$ch])) {
                    $clean[$cat][$ch] = (bool) $channels[$cat][$ch];
                }
            }
        }
        $ok = fn (?string $v) => $v !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) === 1 ? $v : null;
        DB::table('notification_preferences')->updateOrInsert(['user_id' => $u->id], [
            'channels' => json_encode($clean), 'quiet_start' => $ok($start), 'quiet_end' => $ok($end), 'updated_at' => now(), 'created_at' => now(),
        ]);
    }
}
