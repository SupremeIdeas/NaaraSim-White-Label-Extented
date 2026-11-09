<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;
use App\Services\Pricing\CurrencyService;
use App\Services\Pricing\Fx\FxRates;
use App\Support\TopupSettings;
use Throwable;

/**
 * ADMIN-ONLY proof sheet for top-up pricing: for a country and a few face values, what the supplier charges us, what we charge, and how that compares with the
 * neutral benchmark "the same face value at the live mid-market exchange rate" (what a par-priced local supplier or a bank app effectively charges before
 * its own fee). Every number comes from the synced catalogue and the same QuoteService the customer is quoted with, so the sheet cannot disagree with checkout.
 * Cost is shown here and ONLY here (admin screens and the console command); nothing in this class feeds a customer view.
 */
class TopupRateCheck
{
    public function __construct(private QuoteService $quotes, private CurrencyService $fx, private FxRates $feed) {}

    /** Mid-market local-currency units per USD from the independent feed (not our own marked-up display rate), or null when the feed is down. */
    public function midRate(string $currency): ?float
    {
        $currency = strtoupper($currency);
        if ($currency === 'USD') {
            return 1.0;
        }
        try {
            $r = (float) ($this->feed->display()[$currency] ?? 0);
        } catch (Throwable) {
            $r = 0.0;
        }

        return $r > 0 ? $r : null;
    }

    /**
     * @param  list<float>  $faces  face values to check (local currency); the nearest fixed product to each is used
     * @return array{currency:string, mid:?float, rows:list<array<string,mixed>>}
     */
    public function airtime(string $country, array $faces = [500, 1000, 2000, 5000], ?string $operator = null): array
    {
        $country = strtoupper($country);
        $products = TopupProduct::query()->where('active', true)->where('country_iso', $country)->where('type', 'airtime')->where('is_fixed', true)
            ->when($operator, fn ($q) => $q->where('operator_name', $operator))->get()->makeVisible(['cost_native', 'cost_ratio', 'cost_currency']);
        $currency = (string) ($products->first()->currency ?? 'USD');
        $mid = $this->midRate($currency);
        $rows = [];
        foreach ($products->groupBy('operator_name') as $op => $list) {
            foreach ($faces as $face) {
                /** @var TopupProduct|null $p */
                $p = $list->sortBy(fn ($x) => abs((float) $x->fixed_amount - $face))->first();
                if (! $p || abs((float) $p->fixed_amount - $face) > max(1, $face * 0.05)) {
                    continue;
                }
                try {
                    $q = $this->quotes->price($p, (float) $p->fixed_amount);
                } catch (Throwable) {
                    continue;
                }
                $parUsd = $mid ? round((float) $p->fixed_amount / $mid, 4) : null;
                $rows[] = [
                    'operator' => $op, 'supplier' => $p->provider_code, 'face' => (float) $p->fixed_amount,
                    'cost_usd' => (float) $q['cost'], 'price_usd' => (float) $q['price'], 'par_usd' => $parUsd,
                    'markup_on_cost_pct' => $q['cost'] > 0 ? round(($q['price'] / $q['cost'] - 1) * 100, 2) : null,
                    'supplier_vs_par_pct' => $parUsd ? round(($q['cost'] / $parUsd - 1) * 100, 2) : null,
                    'price_vs_par_pct' => $parUsd ? round(($q['price'] / $parUsd - 1) * 100, 2) : null,
                    'effective_rate' => $q['price'] > 0 ? round((float) $p->fixed_amount / $q['price'], 2) : null,
                ];
            }
        }

        return ['currency' => $currency, 'mid' => $mid, 'rows' => $rows, 'settings' => [
            'markup_pct' => TopupSettings::float('markup_pct'), 'min_profit_usd' => TopupSettings::float('min_profit_usd'), 'fx_buffer_pct' => TopupSettings::float('fx_buffer_pct'),
        ]];
    }
}
