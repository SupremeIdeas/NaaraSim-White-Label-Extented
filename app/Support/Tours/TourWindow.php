<?php

namespace App\Support\Tours;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The new-user window. Auto prompts and (by default) the replay hub are offered for `window_days` from account creation; accounts older than the
 * rollout count from the rollout date, so nobody is greeted with a "new user" tour the day it ships. 0 days = never expires.
 * `hub_visibility_after_window`: hide | keep (the owner's wording "disappears" is ambiguous, so it is a single switch).
 */
final class TourWindow
{
    public static function days(): int
    {
        return max(0, (int) self::setting('tours.window_days', config('tours.window_days', 30)));
    }

    public static function hubAfterWindow(): string
    {
        return self::setting('tours.hub_visibility_after_window', config('tours.hub_visibility_after_window', 'hide')) === 'keep' ? 'keep' : 'hide';
    }

    public static function rolloutAt(): \Illuminate\Support\Carbon
    {
        $v = self::setting('tours.rollout_at', null);
        if (! $v) {
            $v = now()->toIso8601String();
            try {
                Setting::setValue('tours.rollout_at', $v, 'tours');
            } catch (\Throwable) {
            }
        }

        return \Illuminate\Support\Carbon::parse($v);
    }

    public static function startedAt(User $u): \Illuminate\Support\Carbon
    {
        $rollout = self::rolloutAt();

        return ($u->created_at && $u->created_at->gt($rollout)) ? $u->created_at : $rollout;
    }

    public static function open(User $u): bool
    {
        $d = self::days();

        return $d === 0 || self::startedAt($u)->copy()->addDays($d)->isFuture();
    }

    public static function hubVisible(User $u): bool
    {
        return self::open($u) || self::hubAfterWindow() === 'keep';
    }

    public static function autoEnabled(User $u): bool
    {
        if (! Schema::hasTable('tour_user_settings')) {
            return false;
        }
        $row = DB::table('tour_user_settings')->where('user_id', $u->id)->first();

        return self::open($u) && (bool) ($row->auto_tours_enabled ?? true);
    }

    public static function setAuto(User $u, bool $on): void
    {
        DB::table('tour_user_settings')->updateOrInsert(['user_id' => $u->id], ['auto_tours_enabled' => $on, 'updated_at' => now(), 'created_at' => now()]);
    }

    private static function setting(string $key, mixed $default): mixed
    {
        try {
            return Setting::getValue($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
