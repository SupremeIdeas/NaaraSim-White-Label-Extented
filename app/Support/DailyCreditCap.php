<?php

namespace App\Support;

use App\Models\CreditLedger;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Daily NaaraCredit earning cap for the Hunt (BUILD-9 §4). One ceiling across
 * BOTH follow claims (platform + brand handles) and video-watch claims — not per
 * brand. The check runs BEFORE a grant using the sum before this claim, so a
 * user can complete the one claim that tips them slightly over the cap, but the
 * NEXT attempt is blocked (a deliberate simplicity choice, not a bug). Resets at
 * UTC midnight. The limit is admin-configurable (default 100).
 */
class DailyCreditCap
{
    private const SETTING = 'credits.daily_hunt_cap';

    private const DEFAULT_LIMIT = 100.0;

    /** Credit sources that count toward the shared daily cap. */
    public const SOURCES = ['social_follow', 'brand_follow', 'brand_video'];

    public const MESSAGE = 'You’ve hit today’s NaaraCredit limit from following and watching. Come back tomorrow for more.';

    public static function limit(): float
    {
        try {
            $v = (float) Setting::getValue(self::SETTING, self::DEFAULT_LIMIT);
        } catch (\Throwable) {
            return self::DEFAULT_LIMIT;
        }

        return $v > 0 ? $v : self::DEFAULT_LIMIT;
    }

    /** Credits earned today (UTC) from the Hunt sources. */
    public static function usedToday(User $user): float
    {
        return (float) CreditLedger::where('user_id', $user->id)
            ->where('type', 'earn')
            ->whereIn('source', self::SOURCES)
            ->where('created_at', '>=', Carbon::now('UTC')->startOfDay())
            ->sum('amount');
    }

    /** True when the user has already reached today's cap (block the next claim). */
    public static function isReached(User $user): bool
    {
        return self::usedToday($user) >= self::limit();
    }

    public static function remaining(User $user): float
    {
        return max(0.0, round(self::limit() - self::usedToday($user), 2));
    }
}
