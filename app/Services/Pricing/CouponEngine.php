<?php

namespace App\Services\Pricing;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\PricingEngineLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CouponEngine (Module 31) — the ONE place a coupon touches a price.
 *
 * Money-safety: a coupon reduces RETAIL only, and the result is clamped to the
 * same floor MarginGuard enforces — provider cost + minimum profit. The floor
 * cannot be disabled, so no coupon can ever sell at or below wholesale. When a
 * discount would cross the floor the price is trimmed UP to the floor, the
 * clamp is flagged on the redemption row, and the calculation is logged to
 * pricing_engine_logs (guard_active = coupon_floor).
 */
class CouponEngine
{
    /**
     * Find a coupon a given user may redeem right now for a product.
     * Returns null when the code is unknown, exhausted, expired, off-product,
     * or the user already used it their allowed number of times.
     */
    public function usable(?string $code, User $user, string $product): ?Coupon
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon || ! $coupon->isRedeemable() || ! $coupon->covers($product)) {
            return null;
        }

        $used = CouponRedemption::where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)->count();

        return $used < $coupon->per_user_limit ? $coupon : null;
    }

    /**
     * Discounted price for a retail/cost pair, never below the margin floor.
     *
     * @return array{price: float, saved: float, clamped: bool}
     */
    public function price(Coupon $coupon, float $retail, float $cost, string $product, ?float $adminMargin = null, ?float $merchantMargin = null): array
    {
        $requested = round($retail * (1 - (float) $coupon->percent_off / 100), 4);

        // Margin-safe floor (discount-floor blueprint §3): percentage-of-margin,
        // with the old absolute cost+minProfit kept underneath (whichever is
        // higher wins). The SMS lane keeps its own smaller absolute floor.
        $minProfit = $product === 'esim'
            ? (float) Setting::getValue('pricing.minimum_profit_usd', 0.50)
            : (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $floor = app(DiscountMarginGuard::class)->floor(
            $cost,
            $adminMargin ?? ($retail - $cost), // non-merchant fallback: whole margin
            $merchantMargin,
            $minProfit,
        );

        $final = max($requested, $floor);
        $final = min($final, $retail); // a coupon can never raise the price

        if ($final > $requested) {
            // Logged as margin_guard — it is the same floor. The provider field
            // ("coupon:CODE") marks it as a coupon clamp in the audit trail.
            PricingEngineLog::create([
                'plan_id' => null,
                'provider' => 'coupon:'.$coupon->code,
                'cost_price' => $cost,
                'markup_used' => (float) $coupon->percent_off,
                'computed_retail' => $requested,
                'final_retail' => $final,
                'guard_active' => 'margin_guard',
                'guard_delta' => round($final - $requested, 4),
            ]);
        }

        return [
            'price' => $final,
            'saved' => round($retail - $final, 4),
            'clamped' => $final > $requested,
        ];
    }

    /**
     * Record a successful redemption (call ONLY after the order persisted).
     * The counter update is atomic so max_redemptions holds under concurrency.
     */
    public function redeem(Coupon $coupon, User $user, string $product, string $reference, float $listPrice, float $paidPrice, bool $clamped): CouponRedemption
    {
        return DB::transaction(function () use ($coupon, $user, $product, $reference, $listPrice, $paidPrice, $clamped) {
            Coupon::whereKey($coupon->id)->lockForUpdate()->increment('times_redeemed');

            return CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'product' => $product,
                'reference' => $reference,
                'list_price' => $listPrice,
                'paid_price' => $paidPrice,
                'amount_saved' => round($listPrice - $paidPrice, 4),
                'floor_clamped' => $clamped,
            ]);
        });
    }
}
