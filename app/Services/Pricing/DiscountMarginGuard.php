<?php

namespace App\Services\Pricing;

use App\Models\Setting;

/**
 * The single source of truth for the floor a combined coupon + NaaraCredit
 * discount may never cross (margin-safe discount floor blueprint §3). Previously
 * the same `cost + minimum_profit` math was duplicated inline in
 * CouponEngine::price() and CreditService::quoteRedemption(); both now defer here.
 *
 * The floor is percentage-OF-MARGIN, not a flat dollar amount and not a
 * percentage of price — so a high-margin plan can no longer be discounted down to
 * a few cents of profit while every old check still reported "compliant". The old
 * absolute `cost + minimum_profit` floor is KEPT underneath and the SAFER (higher)
 * of the two always wins, so this is strictly additive protection: no plan becomes
 * more discountable than it is today, thin-margin plans keep their absolute floor,
 * high-margin plans gain the percentage protection they previously lacked.
 *
 * Money-safety: this never exposes cost — it returns a price floor only.
 */
class DiscountMarginGuard
{
    /**
     * @param  float       $cost            provider wholesale cost
     * @param  float       $adminMargin     the admin's baseline profit (plainRetail − cost)
     * @param  float|null  $merchantMargin  null = non-merchant sale (simple cap); a
     *                                       value = merchant lane (retail − plainRetail),
     *                                       protecting admin + merchant portions separately
     * @param  float|null  $absoluteMinProfit  overrides pricing.minimum_profit_usd
     *                                          (e.g. the SMS lane's own smaller floor)
     */
    public function floor(float $cost, float $adminMargin, ?float $merchantMargin = null, ?float $absoluteMinProfit = null): float
    {
        $minProfit = $absoluteMinProfit ?? (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $absoluteFloor = $cost + $minProfit;

        $adminMargin = max(0.0, $adminMargin);

        if ($merchantMargin === null) {
            // Non-merchant sale: a single order's combined discount may consume at
            // most discount_margin_cap_pct% of the admin's margin.
            $capPct = (float) Setting::getValue('pricing.discount_margin_cap_pct', 30);
            $pctFloor = $cost + $adminMargin * (1 - $this->clampPct($capPct) / 100);
        } else {
            // Merchant lane: the admin keeps ≥ (100 − admin_pct)% of their margin,
            // and the merchant keeps ≥ (100 − merchant_pct)% of theirs — so a
            // coupon can never zero the merchant's earning (fixes the accrual bug).
            $merchantMargin = max(0.0, $merchantMargin);
            $adminPct = (float) Setting::getValue('pricing.merchant_discount_admin_pct', 20);
            $merchantPct = (float) Setting::getValue('pricing.merchant_discount_merchant_pct', 10);
            $pctFloor = $cost
                + $adminMargin * (1 - $this->clampPct($adminPct) / 100)
                + $merchantMargin * (1 - $this->clampPct($merchantPct) / 100);
        }

        // The SAFER (higher) floor always wins.
        return round(max($absoluteFloor, $pctFloor), 4);
    }

    private function clampPct(float $pct): float
    {
        return max(0.0, min(100.0, $pct));
    }
}
