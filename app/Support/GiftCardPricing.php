<?php

namespace App\Support;

use App\Models\GiftCardProduct;
use App\Services\Pricing\PricingEngine;

/**
 * Naara Gift customer pricing. Derives the PROVIDER COST for a face value from
 * the private cost_meta, then returns the RETAIL through PricingEngine — the
 * provider's own suggested price is never shown, cost is never exposed. Display
 * calls don't log (only the authoritative purchase quote does, in Phase 3).
 */
class GiftCardPricing
{
    public function __construct(private PricingEngine $pricing) {}

    /** Retail (USD) the customer pays for a given face value. */
    public function retail(GiftCardProduct $product, float $face, bool $log = false): float
    {
        return $this->pricing->giftCardRetail($this->cost($product, $face), $product->provider, $log, $this->usdFace($product, $face));
    }

    /**
     * Denomination options for the brand-detail UI, priced at retail.
     *
     * @return array{type:string, options?:array, min?:float, max?:float}
     */
    public function denominations(GiftCardProduct $product): array
    {
        if ($product->isRange()) {
            return [
                'type' => 'RANGE',
                'min' => (float) $product->min_amount,
                'max' => (float) $product->max_amount,
            ];
        }

        // A provider that lists one offer per denomination: merge the siblings' faces into one list.
        $rows = $product->exists ? $product->siblings()->get() : collect([$product]);
        $rows = $rows->isEmpty() ? collect([$product]) : $rows;
        $options = $rows->flatMap(fn ($row) => collect((array) $row->fixed_denominations)->filter(fn ($f) => is_numeric($f) && (float) $f > 0)
            ->map(fn ($f) => ['face' => (float) $f, 'retail' => $this->retail($row, (float) $f)]))
            ->unique('face')->sortBy('face')->values()->all();

        return ['type' => 'FIXED', 'options' => $options];
    }

    /**
     * The provider cost for a face value — PRIVATE (never returned to the
     * client). Explicit per-provider dispatch: a provider we don't recognize
     * throws rather than silently falling through to face=cost (money-safety
     * rule 1 — retail must be cost+margin, and "cost" must come from a basis
     * we've actually reasoned about for that provider, never a default).
     */
    private function cost(GiftCardProduct $product, float $face): float
    {
        $meta = (array) $product->cost_meta;

        return match ($product->provider) {
            'reloadly' => $this->reloadlyCost($product, $meta, $face),
            // Zendit lists an exact wholesale cost for every offer (cost.discount vs the face price), so we use it: the customer-facing card can then be
            // sold at face while the discount is our margin, and the offers whose real cost is ABOVE face are never sold below cost.
            'zendit' => $this->zenditCost($meta, $face),
            // Bitrefill quotes its cost in satoshis (no USD discount in the catalogue) and Tillo in its own terms: face value is the conservative basis
            // (never unsafe, may under-capture margin) until a live USD rate is confirmed for the account.
            'bitrefill', 'tillo' => round($face, 4),
            default => throw new \InvalidArgumentException(
                "GiftCardPricing has no cost basis defined for provider [{$product->provider}] — add one before pricing it."
            ),
        };
    }

    /**
     * The face value in USD, or null when the card is denominated in another currency (a 5,000 naira face is not $5,000): face-value pricing only
     * applies to USD faces, and every other card is priced from its USD cost plus the markup.
     */
    private function usdFace(GiftCardProduct $product, float $face): ?float
    {
        $ccy = strtoupper((string) $product->currency);

        return ($ccy === 'USD' || $ccy === '') ? $face : null;
    }

    /**
     * Zendit wholesale cost in USD for a face (USD) value: face x (1 - discount), plus any flat fee and percentage fee, rounded UP to the cent.
     * The discount is capped at 25% either way as a sanity bound: a feed error that claims 80% off must not make us under-price, and the offers that
     * cost more than 25% above face (provider data glitches) are withheld at sync time (see ZenditVoucherService::map).
     */
    private function zenditCost(array $meta, float $face): float
    {
        $cost = (array) ($meta['cost'] ?? []);
        if (! isset($cost['discount']) || ! is_numeric($cost['discount'])) {
            return round($face, 4);   // an older synced row without the cost object: the conservative face basis
        }
        $discount = max(-0.25, min(0.25, (float) $cost['discount']));
        $divisor = (int) ($cost['currencyDivisor'] ?? 100) ?: 100;
        $fee = is_numeric($cost['fee'] ?? null) ? (float) $cost['fee'] / $divisor : 0.0;
        $feePct = is_numeric($cost['feePct'] ?? null) ? (float) $cost['feePct'] : 0.0;

        return ceil((($face * (1 - $discount)) + $fee + ($face * $feePct)) * 100 - 1e-9) / 100;
    }

    private function reloadlyCost(GiftCardProduct $product, array $meta, float $face): float
    {
        $discount = (float) ($meta['discountPercentage'] ?? 0);
        $fee = (float) ($meta['senderFee'] ?? 0);

        // Base cost is in OUR (sender) currency, NEVER the recipient face — a
        // ₦5,000 face is not a $5,000 cost. Use the recipient→sender map for
        // FIXED and the sender range for RANGE; fall back to face only when
        // the currencies already match (scopeStorefront withholds the rest).
        $base = $this->senderBase($product, $meta, $face);

        return round($base * (1 - $discount / 100) + $fee, 4);
    }

    /** The sender-currency (USD) base amount we pay for a recipient face value. */
    private function senderBase(GiftCardProduct $product, array $meta, float $face): float
    {
        $recipientCcy = $product->currency;
        $senderCcy = $meta['senderCurrencyCode'] ?? null;
        if ($recipientCcy !== null && $recipientCcy === $senderCcy) {
            return $face; // no FX gap
        }

        if (! $product->isRange()) {
            // FIXED: look the face up in the recipient→sender map (keys may be
            // "5000" or "5000.00").
            $map = (array) ($meta['senderMap'] ?? []);
            foreach ([(string) $face, (string) (int) $face, number_format($face, 2, '.', '')] as $k) {
                if (isset($map[$k]) && is_numeric($map[$k])) {
                    return (float) $map[$k];
                }
            }

            return $face; // last resort (only reached for currency-matched cards)
        }

        // RANGE: scale linearly by the sender/recipient max (both provider-given).
        $maxSender = (float) ($meta['maxSender'] ?? 0);
        $maxRecipient = (float) $product->max_amount;
        if ($maxSender > 0 && $maxRecipient > 0) {
            return round($face * ($maxSender / $maxRecipient), 4);
        }

        return $face;
    }
}
