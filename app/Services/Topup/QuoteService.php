<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;
use App\Models\TopupQuote;
use App\Models\User;
use App\Services\Pricing\CurrencyService;
use App\Services\Pricing\PricingEngine;
use App\Support\TopupSettings;

/**
 * Golden rule for top-ups: price = (our cost in USD x FX buffer) x (1 + markup), floored at cost + minimum profit by PricingEngine/MarginGuard.
 * A quote locks that price for a few minutes; the order always uses the quote and is never re-priced after payment. Cost never leaves here.
 */
class QuoteService
{
    public function __construct(private PricingEngine $pricing, private CurrencyService $fx) {}

    /** Our USD cost for sending $sendAmount (what the recipient receives) on this product. */
    public function costUsd(TopupProduct $p, float $sendAmount): float
    {
        $native = $p->is_fixed ? (float) $p->cost_native : (float) $p->cost_ratio * $sendAmount;
        if ($native <= 0) {
            throw new TopupException('This top-up is not available right now.');
        }
        $cur = strtoupper((string) ($p->cost_currency ?: 'USD'));
        if ($cur === 'USD') {
            return round($native, 4);
        }
        // Non-USD cost (VTpass NGN, Reloadly/Ding account currency): convert and add the FX buffer.
        $usd = $this->fx->toUsd($native, $cur);
        $usd = $usd > 0 ? $usd : $native / max(0.0001, $this->fx->rate($cur));

        return round($usd * (1 + TopupSettings::float('fx_buffer_pct') / 100), 4);
    }

    /** Customer-facing price for a product at an amount (no persistence). */
    public function price(TopupProduct $p, float $sendAmount, bool $log = false): array
    {
        $cost = $this->costUsd($p, $sendAmount);
        $r = $this->pricing->topupRetail($cost, $p->provider_code, $p->country_iso, $p->operator_name, $log);

        return ['cost' => $cost, 'price' => $r['price'], 'markup_pct' => $r['markup_pct']];
    }

    /** The exchange rate the customer is effectively paying at, for the review screen: local value delivered per US dollar charged (never cost). Null for USD products. */
    public function rateLine(TopupQuote $q): ?array
    {
        $cur = strtoupper((string) $q->send_currency);
        $usd = (float) $q->list_price_usd;
        if ($cur === 'USD' || $usd <= 0) {
            return null;
        }
        $asOf = null;
        try {
            $asOf = $this->fx->rateAsOf($cur);
        } catch (\Throwable) {
        }

        return ['currency' => $cur, 'per_usd' => round((float) $q->send_amount / $usd, 2), 'as_of' => $asOf];
    }

    /** Days a bundle lasts, from the supplier's validity text ("7 days", "1 week", "30 Days", "Monthly"); 0 when unknown. */
    public static function validityDays(?string $text): int
    {
        $t = strtolower((string) $text);
        if (preg_match('/(\d+)\s*(days?|d)\b/', $t, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(weeks?|w)\b/', $t, $m)) {
            return (int) $m[1] * 7;
        }
        if (preg_match('/(\d+)\s*(months?|m)\b/', $t, $m)) {
            return (int) $m[1] * 30;
        }

        return str_contains($t, 'month') ? 30 : (str_contains($t, 'week') ? 7 : (str_contains($t, 'day') ? 1 : 0));
    }

    public function assertAmount(TopupProduct $p, float $amount): float
    {
        $amount = round($amount, 2);
        if ($p->is_fixed) {
            return (float) $p->fixed_amount;
        }
        if ($amount < (float) $p->min_amount || $amount > (float) $p->max_amount) {
            throw new TopupException('Enter an amount between '.number_format((float) $p->min_amount, 2).' and '.number_format((float) $p->max_amount, 2).' '.$p->currency.'.');
        }

        return $amount;
    }

    public function create(User $user, TopupProduct $p, string $phoneE164, float $sendAmount, bool $useCredits = true): TopupQuote
    {
        if (! $p->active) {
            throw new TopupException('That top-up is no longer available. Please pick another.');
        }
        $amount = $this->assertAmount($p, $sendAmount);
        $q = $this->price($p, $amount, log: true);
        // Naara Credit discount (data only, a small slice of our profit, see TopupCredit). price_usd is what the customer pays from the wallet.
        $disc = $useCredits ? TopupCredit::quote($user, $p, (float) $q['price'], (float) $q['cost']) : ['usd' => 0.0, 'credits' => 0.0];
        $pay = round((float) $q['price'] - $disc['usd'], 4);

        return TopupQuote::create([
            'user_id' => $user->id, 'product_id' => $p->id, 'phone_e164' => $phoneE164, 'send_amount' => $amount, 'send_currency' => $p->currency,
            'cost_usd' => $q['cost'], 'price_usd' => $pay, 'list_price_usd' => $q['price'], 'credit_usd' => $disc['usd'], 'credits' => $disc['credits'], 'markup_pct' => $q['markup_pct'],
            'expires_at' => now()->addSeconds(max(60, TopupSettings::int('quote_ttl_seconds'))),
        ]);
    }
}
