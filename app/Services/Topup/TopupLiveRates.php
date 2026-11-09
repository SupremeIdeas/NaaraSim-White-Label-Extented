<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;
use App\Support\TopupSettings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ADMIN-ONLY live rate sheet. Unlike TopupRateCheck (which reads the synced catalogue), this calls EVERY configured supplier's own API right now for one
 * country and lays the answers side by side: what each supplier sells to us for the product, what we would charge (same QuoteService/PricingEngine the
 * customer is quoted with), the margin in dollars and percent, and two benchmarks: (1) "par" = the same face value at the live mid-market exchange rate,
 * (2) the cheapest supplier for the identical product. Nothing is written to the catalogue and nothing here reaches a customer view.
 *
 * Guard flags are advisory: they tell the admin where a contract or a route is out of line, they never change a price.
 */
class TopupLiveRates
{
    /** Over this much above par, a customer will find a cheaper rate elsewhere. */
    public const OVER_PAR_PCT = 10.0;

    /** Supplier dearer than par by more than this: the contract discount is not covering the exchange rate. */
    public const SUPPLIER_OVER_PAR_PCT = 3.0;

    public function __construct(private QuoteService $quotes, private TopupRateCheck $check, private ProviderRouter $router) {}

    /**
     * @param  string  $type  airtime | data
     * @param  list<float>  $faces  airtime face values to sample (local currency) for range products and to select fixed ones
     * @return array<string, mixed>
     */
    public function run(string $country, string $type = 'airtime', array $faces = [], bool $force = false): array
    {
        $country = strtoupper($country);
        $type = $type === 'data' ? 'data' : 'airtime';
        $key = 'topup.liverates.'.$country.'.'.$type.'.'.md5(json_encode($faces));
        if (! $force && ($hit = Cache::get($key))) {
            return $hit;
        }

        $routed = $this->router->chain($country, $type);
        $providers = [];
        $rows = [];
        $currency = null;
        foreach (TopupProviders::all() as $code => $adapter) {
            $meta = TopupProviders::META[$code] ?? ['name' => ucfirst($code)];
            $info = ['code' => $code, 'name' => $meta['name'], 'state' => 'off', 'error' => null, 'ms' => null, 'listed' => 0, 'rows' => 0, 'routed' => in_array($code, $routed, true)];
            if (! $adapter->configured()) {
                $info['error'] = 'Keys not saved';
                $providers[$code] = $info;

                continue;
            }
            $t0 = microtime(true);
            try {
                $list = $adapter->listProducts($country);
                $info['state'] = $list === [] ? 'empty' : 'ok';
                $info['listed'] = count($list);
            } catch (Throwable $e) {
                $info['state'] = 'error';
                $info['error'] = substr($e->getMessage(), 0, 160);
                $list = [];
            }
            $info['ms'] = (int) round((microtime(true) - $t0) * 1000);
            foreach ($list as $dto) {
                $row = $this->row($code, $dto, $type, $faces);
                if ($row) {
                    $currency ??= $row['currency'];
                    $rows[] = $row;
                    $info['rows']++;
                }
            }
            $providers[$code] = $info;
        }

        $currency ??= 'USD';
        $mid = $this->check->midRate($currency);
        $rows = $this->enrich($rows, $mid, $routed, $country);
        foreach ($providers as $code => &$p) {
            $mine = array_values(array_filter($rows, fn ($r) => $r['provider'] === $code));
            $p['avg_supplier_vs_par'] = $this->avg(array_column($mine, 'supplier_vs_par_pct'));
            $p['avg_margin_pct'] = $this->avg(array_column($mine, 'margin_pct'));
            $p['avg_price_vs_par'] = $this->avg(array_column($mine, 'price_vs_par_pct'));
            $p['cheapest_count'] = count(array_filter($mine, fn ($r) => $r['cheapest']));
        }
        unset($p);

        $out = [
            'country' => $country, 'type' => $type, 'currency' => $currency, 'mid' => $mid, 'as_of' => now()->toIso8601String(), 'routed' => $routed,
            'settings' => ['markup_pct' => TopupSettings::float('markup_pct'), 'min_profit_usd' => TopupSettings::float('min_profit_usd'), 'fx_buffer_pct' => TopupSettings::float('fx_buffer_pct')],
            'providers' => $providers, 'rows' => $rows, 'alerts' => $this->alerts($rows),
        ];
        Cache::put($key, $out, 600);

        return $out;
    }

    /** @param  list<float>  $faces */
    private function row(string $code, \App\Services\Topup\DTO\ProductDTO $dto, string $type, array $faces): ?array
    {
        $isData = in_array($dto->type, ['data', 'combo'], true);
        if (($type === 'data') !== $isData) {
            return null;
        }
        $p = new TopupProduct();
        $p->forceFill($dto->toRow($code));
        $p->is_fixed = (bool) $dto->isFixed;
        try {
            if ($dto->isFixed) {
                $send = (float) $dto->fixedAmount;
                if ($type === 'airtime' && $faces !== [] && ! $this->nearAny($send, $faces)) {
                    return null;
                }
            } else {
                $sample = $faces !== [] ? $this->clamp($faces[(int) floor(count($faces) / 2)], $dto) : (float) $dto->minAmount;
                $send = $sample;
            }
            if ($send <= 0) {
                return null;
            }
            $q = $this->quotes->price($p, $send);
        } catch (Throwable) {
            return null;
        }
        $cost = (float) $q['cost'];
        $price = (float) $q['price'];

        return [
            'provider' => $code, 'operator' => $dto->operatorName, 'name' => $dto->name, 'sku' => $dto->providerSku, 'type' => $isData ? 'data' : 'airtime',
            'currency' => strtoupper($dto->currency), 'face' => $send, 'is_range' => ! $dto->isFixed, 'data_mb' => $dto->dataMb, 'unlimited' => $dto->dataUnlimited,
            'days' => QuoteService::validityDays($dto->validityText), 'validity' => $dto->validityText,
            'cost_native' => $dto->isFixed ? $dto->costNative : ($dto->costRatio !== null ? round($dto->costRatio * $send, 4) : null), 'cost_currency' => $dto->costCurrency ? strtoupper($dto->costCurrency) : 'USD',
            'cost_usd' => $cost, 'price_usd' => $price, 'margin_usd' => round($price - $cost, 4),
            'margin_pct' => $price > 0 ? round(($price - $cost) / $price * 100, 2) : null, 'markup_pct' => $cost > 0 ? round(($price / $cost - 1) * 100, 2) : null,
        ];
    }

    /** Adds the benchmarks that need the whole set: par, supplier-vs-par, cheapest supplier per identical product. */
    private function enrich(array $rows, ?float $mid, array $routed, string $country): array
    {
        $groups = [];
        foreach ($rows as $i => &$r0) {
            $r0['op_key'] = $this->opKey($r0['operator'], $country);
        }
        unset($r0);
        foreach ($rows as $i => $r) {
            $groups[$this->groupKey($r)][] = $i;
        }
        foreach ($rows as $i => &$r) {
            $par = ($mid && $r['type'] === 'airtime' && $r['currency'] !== 'USD') ? round($r['face'] / $mid, 4) : ($r['type'] === 'airtime' && $r['currency'] === 'USD' ? $r['face'] : null);
            $r['par_usd'] = $par;
            $r['supplier_vs_par_pct'] = $par ? round(($r['cost_usd'] / $par - 1) * 100, 2) : null;     // negative = they sell to us below face value (our discount)
            $r['price_vs_par_pct'] = $par ? round(($r['price_usd'] / $par - 1) * 100, 2) : null;
            $costs = array_map(fn ($j) => $rows[$j]['cost_usd'], $groups[$this->groupKey($r)]);
            $best = min($costs);
            $r['best_cost_usd'] = $best;
            $r['cheapest'] = abs($r['cost_usd'] - $best) < 0.0001;
            $r['gap_to_best_pct'] = $best > 0 ? round(($r['cost_usd'] / $best - 1) * 100, 2) : 0.0;
            $r['competing'] = count($costs) > 1;
            $r['routed'] = in_array($r['provider'], $routed, true);
        }
        unset($r);
        usort($rows, fn ($a, $b) => [$a['operator'], $a['face'], $a['cost_usd']] <=> [$b['operator'], $b['face'], $b['cost_usd']]);

        return $rows;
    }

    /** Suppliers name one network differently ("MTN", "MTN Nigeria"): group by the resolved brand, else the cleaned name. */
    private function opKey(string $operator, string $country): string
    {
        return \App\Support\TopupLogos::resolveKey($operator, $country)
            ?: strtolower(preg_replace('/[^a-z0-9]/i', '', preg_replace('/\s*\(.*?\)\s*/', '', $operator)));
    }

    private function groupKey(array $r): string
    {
        $op = $r['op_key'];

        return $r['type'] === 'data'
            ? $op.'|d|'.($r['unlimited'] ? 'u' : (int) $r['data_mb']).'|'.(int) $r['days']
            : $op.'|a|'.round($r['face'], 0);
    }

    /** Advisory flags the admin should act on. @return list<array{level:string, text:string}> */
    private function alerts(array $rows): array
    {
        $min = TopupSettings::float('min_profit_usd');
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $tag = $r['provider'].' / '.$r['operator'].' '.($r['type'] === 'data' ? $r['name'] : number_format($r['face']).' '.$r['currency']);
            if ($r['margin_usd'] < $min - 0.0005) {
                $out[] = ['level' => 'bad', 'text' => "$tag: margin \${$r['margin_usd']} is under the \$".number_format($min, 2).' minimum profit.'];
            }
            if (($r['price_vs_par_pct'] ?? 0) > self::OVER_PAR_PCT && $r['routed'] && ! isset($seen['p'.$r['operator']])) {
                $seen['p'.$r['operator']] = 1;
                $out[] = ['level' => 'warn', 'text' => "$tag: our price is {$r['price_vs_par_pct']}% over par; a competitor at par will beat us."];
            }
            if (($r['supplier_vs_par_pct'] ?? 0) > self::SUPPLIER_OVER_PAR_PCT && ! isset($seen['s'.$r['provider'].$r['operator']])) {
                $seen['s'.$r['provider'].$r['operator']] = 1;
                $out[] = ['level' => 'warn', 'text' => "$tag: supplier charges {$r['supplier_vs_par_pct']}% over par; ask for a better contract rate or route elsewhere."];
            }
            if ($r['competing'] && $r['routed'] && $r['gap_to_best_pct'] > 2 && ! isset($seen['g'.$r['operator']])) {
                $seen['g'.$r['operator']] = 1;
                $out[] = ['level' => 'info', 'text' => "$tag: another supplier is {$r['gap_to_best_pct']}% cheaper for the same product; consider moving it up the route."];
            }
        }

        return array_slice($out, 0, 30);
    }

    private function nearAny(float $v, array $faces): bool
    {
        foreach ($faces as $f) {
            if (abs($v - $f) <= max(1, $f * 0.05)) {
                return true;
            }
        }

        return false;
    }

    private function clamp(float $v, \App\Services\Topup\DTO\ProductDTO $dto): float
    {
        return max((float) $dto->minAmount, min((float) ($dto->maxAmount ?: $v), $v));
    }

    /** @param  list<mixed>  $v */
    private function avg(array $v): ?float
    {
        $v = array_values(array_filter($v, fn ($x) => $x !== null));

        return $v === [] ? null : round(array_sum($v) / count($v), 2);
    }
}
