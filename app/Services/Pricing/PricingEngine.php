<?php

namespace App\Services\Pricing;

use App\Models\EsimPlan;
use App\Models\PricingEngineLog;
use App\Models\Setting;

/**
 * PricingEngine — the ONE place a retail price is ever calculated
 * (blueprint Sections 1.4 & 13). No controller, job, or view may compute a
 * price; they all call this service.
 *
 * The Golden Rule: Retail = Provider Cost + NaaraSim Margin. The user always
 * pays retail; NaaraSim always pays cost. Two guards make it impossible to
 * quote below a safe floor:
 *   - Airalo minimum-selling-price guard (contractual, Airalo plans only)
 *   - MarginGuard (never at/below cost + minimum profit) — cannot be disabled
 * Both only ever correct the price UPWARD, and every calculation is logged to
 * pricing_engine_logs.
 */
class PricingEngine
{
    /**
     * Effective retail (USD) for a plan: markup formula, manual override, then
     * the Airalo and MarginGuard floors. This is the value quoted to users
     * (via final_retail_usd) and used by getProfitSummary.
     */
    public function calculateRetail(EsimPlan $plan, bool $log = true): float
    {
        $cost = (float) $plan->cost_price_usd;

        $markup = $plan->override_markup_pct !== null
            ? (float) $plan->override_markup_pct
            : (float) Setting::getValue('pricing.default_markup_pct', 30);

        // Layer 2: markup formula. A manual fixed price bypasses the formula
        // (Layer 3, priority 1) but still passes through the guards below.
        $computed = round($cost * (1 + $markup / 100), 2);
        if ($plan->manual_retail_usd !== null) {
            $computed = (float) $plan->manual_retail_usd;
        }

        $preGuard = $computed;
        $guard = 'none';

        // Airalo minimum-selling-price guard (contractual, Airalo only).
        if ($plan->provider === 'airalo'
            && $plan->airalo_min_price !== null
            && $computed < (float) $plan->airalo_min_price) {
            $computed = (float) $plan->airalo_min_price;
            $guard = 'airalo_min';
        }

        // MarginGuard: never at/below cost + minimum profit. Final safety net.
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $floor = $cost + $minProfit;
        if ($computed < $floor) {
            $computed = round($floor, 4);
            $guard = 'margin_guard';
        }

        if ($log) {
            $this->log(
                planId: $plan->id,
                provider: $plan->provider,
                cost: $cost,
                markup: $markup,
                computed: $preGuard,
                final: $computed,
                guard: $guard,
            );
        }

        return $computed;
    }

    /**
     * Retail (USD) for a per-OTP / SMS charge. Cost is fetched live from the
     * provider before quoting (never hard-coded). Per-provider markup, with a
     * per-SMS minimum-profit floor.
     */
    public function calculateSmsRetail(float $cost, string $provider): float
    {
        $markup = (float) Setting::getValue("pricing.sms_markup_pct.$provider", 40);
        $computed = round($cost * (1 + $markup / 100), 4);

        $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $floor = $cost + $minProfit;
        $final = max($computed, $floor);

        $this->log(
            planId: null,
            provider: $provider,
            cost: $cost,
            markup: $markup,
            computed: $computed,
            final: $final,
            guard: $final > $computed ? 'margin_guard' : 'none',
        );

        return $final;
    }

    /**
     * Retail (USD) for a gift card (Naara Gift). The provider COST for a face
     * value goes in (never the provider's suggested retail), the admin markup is
     * applied, and MarginGuard floors it at cost + minimum profit. Cost is never
     * returned or exposed — only this retail is shown to the customer.
     */
    public function giftCardRetail(float $cost, string $provider = 'giftcard', bool $log = true): float
    {
        $markup = (float) Setting::getValue('pricing.giftcard_markup_pct', 8);
        $computed = round($cost * (1 + $markup / 100), 2);

        $minProfit = (float) Setting::getValue('pricing.giftcard_min_profit', 0.25);
        $final = max($computed, round($cost + $minProfit, 2));

        // Only log the authoritative quote at purchase time, not on every
        // storefront render (which prices many denominations).
        if ($log) {
            $this->log(
                planId: null,
                provider: 'giftcard:'.$provider,
                cost: $cost,
                markup: $markup,
                computed: $computed,
                final: $final,
                guard: $final > $computed ? 'margin_guard' : 'none',
            );
        }

        return $final;
    }

    /**
     * Retail (USD) per-MINUTE for an outbound in-browser call (Live Voice —
     * Part B). Live wholesale cost in, per-provider voice markup applied, floored
     * by a per-minute minimum-profit MarginGuard. Same discipline as SMS: cost is
     * fetched live before quoting and never returned or exposed to the user.
     */
    public function calculateVoiceRetail(float $costPerMin, string $provider = 'twilio'): float
    {
        $markup = (float) Setting::getValue("pricing.voice_markup_pct.$provider", 40);
        $computed = round($costPerMin * (1 + $markup / 100), 4);

        $minProfit = (float) Setting::getValue('pricing.voice_min_profit', 0.02);
        $floor = $costPerMin + $minProfit;
        $final = max($computed, round($floor, 4));

        $this->log(
            planId: null,
            provider: 'voice:'.$provider,
            cost: $costPerMin,
            markup: $markup,
            computed: $computed,
            final: $final,
            guard: $final > $computed ? 'margin_guard' : 'none',
        );

        return $final;
    }

    /**
     * Developer-lane price (USD) for an eSIM plan — the "wholesale + small admin
     * markup" a Developer API client pays (ROADMAP §Layer 2). It is deliberately
     * BELOW retail (a real deal) but MarginGuard still floors it at cost +
     * minimum profit, so the admin never sells at a loss no matter how the
     * developer markup is (mis)configured. Cost is never returned or exposed.
     */
    public function developerEsimPrice(EsimPlan $plan, bool $log = true): float
    {
        $cost = (float) $plan->cost_price_usd;
        $markup = (float) Setting::getValue('pricing.developer_markup_pct', 10);
        $computed = round($cost * (1 + $markup / 100), 4);

        // MarginGuard: never at/below cost + minimum profit (same floor as retail).
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $final = max($computed, round($cost + $minProfit, 4));

        if ($log) {
            $this->log(
                planId: $plan->id,
                provider: 'dev:'.$plan->provider, // marks the developer lane in the audit log
                cost: $cost,
                markup: $markup,
                computed: $computed,
                final: $final,
                guard: $final > $computed ? 'margin_guard' : 'none',
            );
        }

        return $final;
    }

    /**
     * Developer-lane price (USD) for a per-number / OTP charge. Live cost in,
     * developer markup applied, MarginGuard-floored at cost + the per-SMS profit
     * floor. Mirrors calculateSmsRetail but on the cheaper developer markup.
     */
    public function developerSmsPrice(float $cost, string $provider, bool $log = true): float
    {
        $markup = (float) Setting::getValue('pricing.developer_sms_markup_pct', 15);
        $computed = round($cost * (1 + $markup / 100), 4);

        $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $final = max($computed, round($cost + $minProfit, 4));

        if ($log) {
            $this->log(
                planId: null,
                provider: 'dev:'.$provider,
                cost: $cost,
                markup: $markup,
                computed: $computed,
                final: $final,
                guard: $final > $computed ? 'margin_guard' : 'none',
            );
        }

        return $final;
    }

    /**
     * Merchant (reseller) price (USD) for an eSIM plan — retail PLUS an admin-set
     * reseller margin (ROADMAP §Layer 3.2). Stacks ABOVE retail, so the admin's
     * profit (retail − cost) is always kept and the merchant earns the margin
     * (merchant price − retail). The margin is admin-owned (per-merchant override
     * or the global setting); a merchant never sets their own. MarginGuard still
     * floors it — a merchant price can never dip below cost + minimum profit.
     */
    public function merchantEsimPrice(EsimPlan $plan, ?\App\Models\Merchant $merchant = null, bool $log = true, ?float $lockedMarginPct = null): float
    {
        $retail = $this->calculateRetail($plan, false);
        // §3.3 lock-in: a referral customer's margin-at-signup wins over the
        // merchant's current margin. Null → fall back to the live margin.
        $margin = $lockedMarginPct ?? $this->resellerMargin($merchant);
        $computed = round($retail * (1 + $margin / 100), 2);

        $cost = (float) $plan->cost_price_usd;
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $final = max($computed, round($cost + $minProfit, 4));

        if ($log) {
            $this->log(
                planId: $plan->id,
                provider: 'merchant:'.$plan->provider,
                cost: $cost,
                markup: $margin,
                computed: $computed,
                final: $final,
                guard: $final > $computed ? 'margin_guard' : 'none',
            );
        }

        return $final;
    }

    /**
     * Merchant (reseller) price (USD) for a per-number / OTP charge — retail plus
     * the reseller margin, MarginGuard-floored. Mirrors merchantEsimPrice.
     */
    public function merchantSmsPrice(float $cost, string $provider, ?\App\Models\Merchant $merchant = null, bool $log = true, ?float $lockedMarginPct = null): float
    {
        $retail = $this->calculateSmsRetail($cost, $provider);
        $margin = $lockedMarginPct ?? $this->resellerMargin($merchant); // §3.3 lock-in
        $computed = round($retail * (1 + $margin / 100), 4);

        $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $final = max($computed, round($cost + $minProfit, 4));

        if ($log) {
            $this->log(
                planId: null,
                provider: 'merchant:'.$provider,
                cost: $cost,
                markup: $margin,
                computed: $computed,
                final: $final,
                guard: $final > $computed ? 'margin_guard' : 'none',
            );
        }

        return $final;
    }

    /** The reseller margin % for a merchant: per-merchant override, else global. */
    private function resellerMargin(?\App\Models\Merchant $merchant): float
    {
        if ($merchant !== null && $merchant->reseller_margin_pct !== null) {
            return (float) $merchant->reseller_margin_pct;
        }

        return \App\Support\MerchantSettings::resellerMarginPct();
    }

    /**
     * Cost / retail / profit breakdown for a plan (admin-only view). The cost
     * is included here for the admin profit panel and must never be surfaced
     * to end users.
     */
    public function getProfitSummary(EsimPlan $plan, bool $log = true): array
    {
        $cost = (float) $plan->cost_price_usd;
        $retail = $this->calculateRetail($plan, $log);
        $profit = round($retail - $cost, 2);

        return [
            'cost_price' => round($cost, 4),
            'retail_price' => $retail,
            'profit_usd' => $profit,
            'profit_pct' => $cost > 0 ? round($profit / $cost * 100, 1) : 0.0,
        ];
    }

    /**
     * Recompute a plan's computed_retail_usd (the engine price, ignoring any
     * manual override) and persist it. final_retail_usd then follows via the
     * generated COALESCE(manual, computed) column. Call after catalogue sync
     * and whenever the global markup changes.
     */
    public function recompute(EsimPlan $plan): EsimPlan
    {
        $original = $plan->manual_retail_usd;

        // Temporarily ignore the manual override so we store the pure engine
        // price into computed_retail_usd (the override lives in its own column).
        $plan->manual_retail_usd = null;
        $enginePrice = $this->calculateRetail($plan);
        $plan->manual_retail_usd = $original;

        $plan->computed_retail_usd = $enginePrice;
        $plan->save();

        return $plan;
    }

    /**
     * Persist one audit row per calculation. guard_delta is the upward
     * correction a guard applied (>= 0); 0 when no guard fired.
     */
    private function log(?int $planId, string $provider, float $cost, float $markup, float $computed, float $final, string $guard): void
    {
        PricingEngineLog::create([
            'plan_id' => $planId,
            'provider' => $provider,
            'cost_price' => $cost,
            'markup_used' => $markup,
            'computed_retail' => $computed,
            'final_retail' => $final,
            'guard_active' => $guard,
            'guard_delta' => round($final - $computed, 4),
        ]);
    }
}
