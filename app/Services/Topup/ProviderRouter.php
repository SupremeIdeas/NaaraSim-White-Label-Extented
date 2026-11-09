<?php

namespace App\Services\Topup;

use App\Models\TopupOrder;
use App\Models\TopupProduct;
use App\Models\TopupProvider;
use App\Models\TopupRoute;
use App\Services\Topup\Contracts\TopupProviderInterface;

/**
 * Data-driven routing (table topup_routes, edited in Admin > Top-ups > Routes): country + product type -> ordered suppliers. A supplier is
 * eligible only when it is switched on, has keys, and its float is not below its minimum. NO route means the country is not sellable.
 */
class ProviderRouter
{
    /** @return list<string> ordered supplier codes that may serve this country + type right now */
    public function chain(string $country, string $type, ?float $priceUsd = null): array
    {
        $country = strtoupper($country);
        $routes = TopupRoute::where('enabled', true)
            ->where(fn ($q) => $q->whereNull('country_iso')->orWhere('country_iso', '')->orWhere('country_iso', '*')->orWhere('country_iso', $country))
            ->whereIn('product_type', ['*', $type])
            ->orderByRaw("CASE WHEN country_iso = ? THEN 0 ELSE 1 END", [$country])      // a country-specific route outranks the wildcard
            ->orderBy('priority')->get();

        $out = [];
        foreach ($routes as $r) {
            if ($priceUsd !== null && (($r->min_amount_usd !== null && $priceUsd < (float) $r->min_amount_usd) || ($r->max_amount_usd !== null && $priceUsd > (float) $r->max_amount_usd))) {
                continue;
            }
            if (! in_array($r->provider_code, $out, true) && $this->eligible($r->provider_code)) {
                $out[] = $r->provider_code;
            }
        }

        return $out;
    }

    public function eligible(string $code): bool
    {
        if (! isset(TopupProviders::MAP[$code])) {
            return false;
        }
        $row = TopupProvider::where('code', $code)->first();
        if (! $row || ! $row->enabled || ! TopupProviders::get($code)->configured()) {
            return false;
        }

        return ! ($row->last_balance !== null && (float) $row->min_float > 0 && (float) $row->last_balance < (float) $row->min_float);
    }

    /** Is this country sellable at all (any eligible route for airtime or data)? */
    public function sellable(string $country): bool
    {
        return $this->chain($country, 'airtime') !== [] || $this->chain($country, 'data') !== [];
    }

    /** @return list<string> every supplier with a route for this country (ignores float), for the admin screen */
    public function routedCodes(string $country): array
    {
        return TopupRoute::where('enabled', true)->where(fn ($q) => $q->whereNull('country_iso')->orWhere('country_iso', '*')->orWhere('country_iso', strtoupper($country)))
            ->orderBy('priority')->pluck('provider_code')->unique()->values()->all();
    }

    /** The equivalent product at the NEXT supplier in the chain after the one that just failed definitively. */
    public function nextProduct(TopupOrder $order, array $tried): ?TopupProduct
    {
        $chain = $this->chain($order->country_iso, $order->product_type, (float) $order->price_usd);
        foreach ($chain as $code) {
            if (in_array($code, $tried, true)) {
                continue;
            }
            $q = TopupProduct::where('provider_code', $code)->where('country_iso', $order->country_iso)->where('active', true)->where('type', $order->product_type)
                ->whereRaw('LOWER(operator_name) = ?', [strtolower((string) $order->operator_name)]);
            $amount = (float) $order->send_amount;
            $match = (clone $q)->where('is_fixed', true)->whereBetween('fixed_amount', [$amount - 0.01, $amount + 0.01])->where('currency', $order->send_currency)->first()
                ?? (clone $q)->where('is_fixed', false)->where('currency', $order->send_currency)->where('min_amount', '<=', $amount)->where('max_amount', '>=', $amount)->first();
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    public function provider(string $code): TopupProviderInterface
    {
        return TopupProviders::get($code);
    }

    /** The sellable product (first supplier in the chain that has it) matching a saved spec: used by auto-reload, where offers may have moved since it was set up. */
    public function matchProduct(string $country, string $type, string $operator, float $amount, string $currency, ?string $name = null): ?TopupProduct
    {
        foreach ($this->chain($country, $type) as $code) {
            $q = TopupProduct::where('provider_code', $code)->where('country_iso', strtoupper($country))->where('active', true)->where('type', $type)
                ->whereRaw('LOWER(operator_name) = ?', [strtolower($operator)])->where('currency', $currency);
            $fixed = (clone $q)->where('is_fixed', true)->whereBetween('fixed_amount', [$amount - 0.01, $amount + 0.01])->get();
            if ($fixed->isNotEmpty()) {
                return ($name ? $fixed->firstWhere('name', $name) : null) ?? $fixed->first();
            }
            $range = (clone $q)->where('is_fixed', false)->where('min_amount', '<=', $amount)->where('max_amount', '>=', $amount)->first();
            if ($range && $type === 'airtime') {
                return $range;
            }
        }

        return null;
    }
}
