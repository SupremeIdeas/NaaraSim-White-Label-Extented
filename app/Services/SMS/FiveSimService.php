<?php

namespace App\Services\SMS;

use App\Exceptions\FiveSimException;
use App\Exceptions\OutOfStockException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 5sim — global numbers, 180+ countries (blueprint Section 9). Does both
 * activation (one-time OTP) and hosting (rental). Auth via Bearer JWT.
 *
 * Rating discipline (money + reliability): 5sim starts every account at 96;
 * timeouts/cancels lower it, finishing raises it. The router/jobs therefore
 * ALWAYS call finish() on a received code and cancel() promptly on abandonment
 * so ordering is never blocked. Country names are 5sim slugs (nigeria, usa…).
 */
class FiveSimService implements SmsProviderInterface
{
    private function client(): PendingRequest
    {
        // Every call is bounded so a slow/dead provider can never hang the PHP
        // worker (and, with a file session lock, the user's whole session). 3s
        // to connect, 8s total for reads; purchase calls override to 15s.
        return Http::baseUrl(rtrim(config('services.fivesim.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withToken(config('services.fivesim.api_key'))
            ->acceptJson();
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        $operators = $this->rawOperators($country, $service);

        // A specific operator was chosen (Step-3 comparison) — price THAT one so
        // the charge matches what the user picked; must be in stock.
        if ($operator !== null && $operator !== '' && $operator !== 'any') {
            $op = $operators[$operator] ?? null;
            if ($op === null || (int) ($op['count'] ?? 0) <= 0) {
                throw new OutOfStockException("5sim operator {$operator} out of stock for {$service} in {$country}.");
            }

            return (float) ($op['cost'] ?? 0);
        }

        $best = null;
        foreach ($operators as $op) {
            if ((int) ($op['count'] ?? 0) > 0) {
                $cost = (float) ($op['cost'] ?? 0);
                $best = $best === null ? $cost : min($best, $cost);
            }
        }

        if ($best === null) {
            throw new OutOfStockException("5sim out of stock for {$service} in {$country}.");
        }

        return $best;
    }

    /** Raw operator map for a country+service: [operator => [cost,count,rate]]. */
    private function rawOperators(string $country, string $service): array
    {
        $data = Cache::remember("5sim_price_{$country}_{$service}", 300, fn () => $this->client()
            ->get('/guest/prices', ['country' => $country, 'product' => $service])
            ->throw()->json() ?? []);

        return (array) data_get($data, "{$country}.{$service}", []);
    }

    /**
     * Operator comparison rows for a country+service (Numbers V6 §3 — Step 3).
     * COST-only here (internal); the router layers retail on top and never
     * exposes cost. Each row: operator, cost, count (availability), rate
     * (5sim success %). Sorted cheapest-first, in-stock ahead of out-of-stock.
     *
     * @return list<array{operator:string, cost:float, count:int, rate:float}>
     */
    public function operators(string $country, string $service): array
    {
        $rows = [];
        foreach ($this->rawOperators($country, $service) as $name => $op) {
            $rows[] = [
                'operator' => (string) $name,
                'cost' => (float) ($op['cost'] ?? 0),
                'count' => (int) ($op['count'] ?? 0),
                'rate' => (float) ($op['rate'] ?? 0),
            ];
        }
        usort($rows, fn ($a, $b) => [$b['count'] > 0, -$a['cost']] <=> [$a['count'] > 0, -$b['cost']]);

        return $rows;
    }

    /**
     * Cheapest in-stock country for a service across ALL 5sim countries in one
     * call (Smart Buy — auto-best-country). Returns the country slug or null.
     */
    public function cheapestCountryFor(string $service): ?string
    {
        $data = Cache::remember("5sim_prod_{$service}", 300, fn () => $this->client()
            ->get('/guest/prices', ['product' => $service])->throw()->json() ?? []);

        $bestCountry = null;
        $bestCost = null;
        foreach ((array) $data as $country => $products) {
            foreach ((array) data_get($products, $service, []) as $op) {
                if ((int) ($op['count'] ?? 0) > 0) {
                    $cost = (float) ($op['cost'] ?? 0);
                    if ($bestCost === null || $cost < $bestCost) {
                        $bestCost = $cost;
                        $bestCountry = (string) $country;
                    }
                }
            }
        }

        return $bestCountry;
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        $operator = $options['operator'] ?? 'any';
        $query = array_filter([
            'voice' => ($options['voice'] ?? false) ? 1 : null,
            'maxPrice' => $options['max_price'] ?? null,
        ]);

        // Purchase call — a real buy can take a little longer than a price check.
        $json = $this->guard($this->client()->timeout(15)
            ->get("/user/buy/activation/{$country}/{$operator}/{$service}", $query)->json());

        return $this->normalizeBuy($json);
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        $operator = $options['operator'] ?? 'any';
        // Purchase call — allow the longer 15s ceiling for a real rental buy.
        $json = $this->guard($this->client()->timeout(15)
            ->get("/user/buy/hosting/{$country}/{$operator}/{$service}")->json());

        return $this->normalizeBuy($json);
    }

    public function check(string $providerRef): array
    {
        $json = $this->client()->get("/user/check/{$providerRef}")->json() ?? [];
        $status = $this->mapStatus($json['status'] ?? 'PENDING');

        $code = null;
        foreach ($json['sms'] ?? [] as $sms) {
            if (! empty($sms['code'])) {
                $code = (string) $sms['code'];
                break;
            }
        }

        return ['status' => $code ? OtpStatus::RECEIVED : $status, 'code' => $code];
    }

    public function finish(string $providerRef): void
    {
        $this->client()->get("/user/finish/{$providerRef}");
    }

    public function cancel(string $providerRef): void
    {
        $this->client()->get("/user/cancel/{$providerRef}");
    }

    public function balance(): float
    {
        return (float) ($this->client()->get('/user/profile')->json()['balance'] ?? 0);
    }

    public function supportsFullRent(): bool
    {
        return false; // 5sim hosting is per-service
    }

    /**
     * The provider's FULL country list (slug => English label) for the catalogue
     * sync. 5sim is slug-based, matching the buy flow's country format.
     *
     * @return array<string, string>
     */
    public function catalogueCountries(): array
    {
        $data = $this->client()->get('/guest/countries')->throw()->json() ?? [];
        $out = [];
        foreach ($data as $slug => $meta) {
            if (! is_string($slug) || $slug === 'any' || ! is_array($meta)) {
                continue;
            }
            $out[$slug] = (string) (data_get($meta, 'text_en') ?: ucwords(str_replace('_', ' ', $slug)));
        }

        return $out;
    }

    /**
     * The provider's service (product) slugs, unioned across a few
     * high-coverage countries (5sim has no single global-products endpoint).
     *
     * @return list<string>
     */
    public function catalogueServices(): array
    {
        $slugs = [];
        foreach (['russia', 'usa', 'england', 'india'] as $country) {
            try {
                $data = $this->client()->get("/guest/products/{$country}/any")->throw()->json() ?? [];
            } catch (\Throwable) {
                continue;
            }
            foreach ($data as $slug => $meta) {
                if (is_string($slug) && $slug !== '') {
                    $slugs[$slug] = true;
                }
            }
        }

        return array_keys($slugs);
    }

    private function normalizeBuy(array $json): array
    {
        return [
            'provider_ref' => (string) $json['id'],
            'number' => (string) $json['phone'],
            'cost' => (float) ($json['price'] ?? 0),
            'status' => OtpStatus::PENDING,
        ];
    }

    /** 5sim returns an error string (json {error} or plain "no free phones"). */
    private function guard(mixed $json): array
    {
        if (is_string($json)) {
            $json = ['error' => $json];
        }
        $error = $json['error'] ?? null;
        if ($error === null) {
            return $json;
        }

        $lower = strtolower($error);
        if (str_contains($lower, 'no free phones') || str_contains($lower, 'out of stock') || str_contains($lower, 'not enough')) {
            throw new OutOfStockException($error);
        }

        throw new FiveSimException($error);
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'RECEIVED' => OtpStatus::RECEIVED,
            'TIMEOUT' => OtpStatus::TIMEOUT,
            'CANCELED', 'BANNED' => OtpStatus::CANCELED,
            'FINISHED' => OtpStatus::FINISHED,
            default => OtpStatus::PENDING,
        };
    }
}
