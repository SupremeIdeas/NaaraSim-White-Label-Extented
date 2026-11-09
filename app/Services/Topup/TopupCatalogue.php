<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;
use App\Models\TopupProvider;
use App\Support\TopupSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * REAL-TIME offers. When a customer opens a country we ask the routed supplier(s) for the live list (cached for a few seconds/minutes,
 * admin-tunable), upsert it into the normalised snapshot so quotes can point at a stable row, retire rows the supplier no longer lists,
 * and fall back to the last snapshot if the supplier is down, so the page never goes blank. A nightly job refreshes every country too.
 */
class TopupCatalogue
{
    public function __construct(private ProviderRouter $router) {}

    /** Refresh one country from its routed suppliers (respecting the cache window unless $force). @return list<string> suppliers that answered */
    public function refresh(string $country, bool $force = false): array
    {
        $country = strtoupper($country);
        $key = 'topup.live.'.$country;
        if (! $force && Cache::has($key)) {
            return (array) Cache::get($key);
        }

        $answered = [];
        $codes = array_values(array_unique(array_merge($this->router->chain($country, 'airtime'), $this->router->chain($country, 'data'))));
        foreach ($codes as $code) {
            try {
                $products = TopupProviders::get($code)->listProducts($country);
            } catch (Throwable $e) {
                TopupProvider::where('code', $code)->update(['last_error' => substr($e->getMessage(), 0, 250)]);

                continue;
            }
            $this->store($code, $country, $products);
            $answered[] = $code;
        }

        Cache::put($key, $answered, max(5, TopupSettings::int('offer_cache_seconds')));

        return $answered;
    }

    /** @param  list<\App\Services\Topup\DTO\ProductDTO>  $products */
    public function store(string $code, string $country, array $products): void
    {
        $seen = [];
        foreach ($products as $dto) {
            $row = $dto->toRow($code);
            TopupProduct::updateOrCreate(['provider_code' => $code, 'provider_sku' => $row['provider_sku'], 'country_iso' => $row['country_iso']], $row);
            $seen[] = $row['provider_sku'];
        }
        // Retire (never delete: orders reference products) what the supplier stopped listing. An empty answer retires nothing: it is a supplier fault, not "everything is gone".
        if ($seen !== []) {
            TopupProduct::where('provider_code', $code)->where('country_iso', strtoupper($country))->whereNotIn('provider_sku', $seen)->update(['active' => false]);
        }
    }

    /** Countries a customer can pick: those with products from an eligible supplier, plus any routed country not yet fetched. @return Collection<int, string> */
    public function countries(): Collection
    {
        $eligible = collect(TopupProviders::codes())->filter(fn ($c) => $this->router->eligible($c))->values()->all();
        if ($eligible === []) {
            return collect();
        }
        $fromProducts = TopupProduct::whereIn('provider_code', $eligible)->where('active', true)->distinct()->pluck('country_iso');
        // Suppliers also announce their countries (cached for hours), so a fresh install lists countries before the first sync has run.
        foreach ($eligible as $code) {
            try {
                $fromProducts = $fromProducts->merge(TopupProviders::get($code)->countries());
            } catch (Throwable) {
            }
        }

        return $fromProducts->map('strtoupper')->unique()->filter(fn ($c) => $this->router->sellable($c))->sort()->values();
    }

    /**
     * The sellable products for a country, from the first supplier in the routing chain that has the operator, so a customer never sees the
     * same bundle twice. @return Collection<int, TopupProduct>
     */
    public function products(string $country, ?string $type = null, ?string $operator = null): Collection
    {
        $country = strtoupper($country);
        $this->refresh($country);

        $rows = collect();
        foreach (['airtime', 'data'] as $t) {
            if ($type && $type !== $t && ! ($type === 'data' && $t === 'combo')) {
                continue;
            }
            $chain = $this->router->chain($country, $t);
            $byOperator = [];
            foreach ($chain as $code) {
                $q = TopupProduct::where('provider_code', $code)->where('country_iso', $country)->where('active', true)->where('type', $t === 'data' ? 'data' : 'airtime');
                foreach ($q->get() as $p) {
                    $byOperator[strtolower($p->operator_name)][$code][] = $p;
                }
            }
            foreach ($byOperator as $perProvider) {
                foreach ($chain as $code) {            // first supplier in priority order that carries this operator wins
                    if (! empty($perProvider[$code])) {
                        $rows = $rows->merge($perProvider[$code]);
                        break;
                    }
                }
            }
        }

        if ($operator) {
            $rows = $rows->filter(fn ($p) => strcasecmp($p->operator_name, $operator) === 0);
        }

        return $rows->values();
    }

    /** @return Collection<int, string> distinct operator names for the country */
    public function operators(string $country): Collection
    {
        return $this->products($country)->pluck('operator_name')->unique(fn ($n) => strtolower($n))->sort()->values();
    }

    /** Full nightly sync of every routed country. Returns per-supplier counts; alerts when a country drops by more than the configured percent. @return array<string, int> */
    public function syncAll(): array
    {
        $counts = [];
        foreach (TopupProviders::codes() as $code) {
            if (! $this->router->eligible($code)) {
                continue;
            }
            $p = TopupProviders::get($code);
            foreach ($p->countries() as $iso) {
                if (! $this->router->sellable($iso) || ! in_array($code, $this->router->routedCodes($iso), true)) {
                    continue;
                }
                $before = TopupProduct::where('provider_code', $code)->where('country_iso', $iso)->where('active', true)->count();
                try {
                    $list = $p->listProducts($iso);
                } catch (Throwable) {
                    continue;
                }
                $this->store($code, $iso, $list);
                $counts[$code] = ($counts[$code] ?? 0) + count($list);
                $drop = TopupSettings::float('catalogue_drop_alert_pct');
                if ($before >= 10 && count($list) < $before * (1 - $drop / 100)) {
                    \App\Jobs\AlertAdminJob::dispatch(code: 'topup_catalogue_drop', message: "Top-up catalogue for $iso from $code dropped from $before to ".count($list).' products.', context: ['country' => $iso, 'provider' => $code]);
                }
            }
            TopupProvider::where('code', $code)->update(['last_synced_at' => now()]);
        }

        return $counts;
    }
}
