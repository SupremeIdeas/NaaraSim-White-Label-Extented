<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Coupon marketing (owner request). Decides which friendly discount nudge — if
 * any — to show a customer on their dashboard, to turn an idle account into a
 * first purchase and reward regulars:
 *
 *   - FIRST VISIT / never purchased  -> a warm "welcome" flash-sale code.
 *   - Been around a while, still never transacted -> a "we miss you" comeback code.
 *
 * It only ever SURFACES an existing, redeemable coupon (created by the seeder /
 * admin) — the discount itself is still MarginGuard-clamped at checkout by the
 * CouponEngine, so no nudge can ever sell below cost + minimum profit.
 */
class MarketingCoupons
{
    public const WELCOME_CODE = 'WELCOME10';

    public const COMEBACK_CODE = 'COMEBACK15';

    public const FLAG = 'marketing.coupon_nudges';

    /** Master switch (admin) — nudges off by default until the operator opts in. */
    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    /**
     * The nudge to show this user right now, or null. Returns a friendly title +
     * message + the coupon code to apply at checkout.
     *
     * @return array{code: string, percent: int, title: string, message: string, cta: string}|null
     */
    public static function nudgeFor(User $user): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        // Anyone who has already bought something doesn't need enticing.
        if (self::hasTransacted($user)) {
            return null;
        }

        // Older idle accounts get the stronger "comeback" offer; brand-new
        // visitors get the welcome flash sale.
        $isSettled = $user->created_at !== null && $user->created_at->lt(now()->subDays(3));
        $code = $isSettled ? self::COMEBACK_CODE : self::WELCOME_CODE;

        $coupon = self::redeemableFor($code, $user);
        if ($coupon === null) {
            return null;
        }

        $percent = (int) round((float) $coupon->percent_off);
        $first = trim(explode(' ', (string) $user->name)[0] ?? '') ?: 'there';

        return $isSettled
            ? [
                'code' => $coupon->code,
                'percent' => $percent,
                'title' => "We saved you {$percent}% off, {$first}",
                'message' => "Your NaaraSim account is ready and waiting — grab {$percent}% off your first eSIM or number and put it to work. No pressure, just a little nudge.",
                'cta' => 'Use my discount',
            ]
            : [
                'code' => $coupon->code,
                'percent' => $percent,
                'title' => "Welcome, {$first} — here's {$percent}% off",
                'message' => "Kick things off with {$percent}% off your very first purchase. Data for 190+ countries or a number in seconds — your pick.",
                'cta' => 'Claim my welcome offer',
            ];
    }

    /** Has the user ever completed a purchase (eSIM or number)? Cached briefly. */
    private static function hasTransacted(User $user): bool
    {
        return Cache::remember("mkt.transacted.{$user->id}", 300, function () use ($user) {
            return $user->esimOrders()->exists()
                || $user->smsOrders()->whereNotIn('status', ['timeout', 'cancelled'])->exists();
        });
    }

    /** The coupon for a code, only if it's live and this user can still use it. */
    private static function redeemableFor(string $code, User $user): ?Coupon
    {
        $coupon = Coupon::where('code', $code)->first();
        if ($coupon === null || ! $coupon->isRedeemable()) {
            return null;
        }
        // Respect the per-user limit so we never dangle a code they can't redeem.
        $used = $coupon->redemptions()->where('user_id', $user->id)->count();
        if ($coupon->per_user_limit !== null && $used >= $coupon->per_user_limit) {
            return null;
        }

        return $coupon;
    }
}
