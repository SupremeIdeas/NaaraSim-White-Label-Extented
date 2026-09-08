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
        return $this->pricing->giftCardRetail($this->cost($product, $face), $product->provider, $log);
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

        $options = collect((array) $product->fixed_denominations)
            ->filter(fn ($f) => is_numeric($f) && (float) $f > 0)
            ->map(fn ($f) => ['face' => (float) $f, 'retail' => $this->retail($product, (float) $f)])
            ->values()->all();

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
            // Zendit, Bitrefill, Tillo: none of the three exposes a wholesale
            // rate distinct from the offer/package/face value in what we sync
            // from their catalogue (unlike Reloadly's explicit
            // discountPercentage + sender-currency map). Face value is used as
            // the cost basis — conservative (we may under-capture margin where
            // a provider's real wholesale cost is lower) but never unsafe
            // (retail is always cost+margin on top of a real, chargeable
            // amount, never below it). Revisit per-provider if/when a rate
            // field is confirmed against their live account terms.
            'zendit', 'bitrefill', 'tillo' => round($face, 4),
            default => throw new \InvalidArgumentException(
                "GiftCardPricing has no cost basis defined for provider [{$product->provider}] — add one before pricing it."
            ),
        };
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
