<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Airalo — SECONDARY provider (blueprint Section 5.3).
 *
 * The blueprint recommends the official airalo/airalo-php-sdk for automatic
 * OAuth token refresh. We implement the documented OAuth2 client-credentials
 * flow via Laravel Http instead, so the provider is faked-HTTP testable and
 * carries no unpinned third-party dependency; swapping to the SDK later is
 * isolated to this class. The token is cached until just before it expires.
 *
 * CRITICAL: every package returns net_price (our cost, PRIVATE) AND
 * minimum_selling_price (the contractual floor). Both are stored on sync; the
 * PricingEngine must never quote below minimum_selling_price.
 */
class AiraloService implements EsimProviderInterface
{
    private const TOKEN_CACHE_KEY = 'airalo:access_token';

    private function baseUrl(): string
    {
        return rtrim(config('services.airalo.base_url'), '/');
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addHours(23), function () {
            $response = Http::timeout(8)->connectTimeout(3)
                ->asMultipart()
                ->acceptJson()
                ->post($this->baseUrl().'/token', [
                    'client_id' => config('services.airalo.client_id'),
                    'client_secret' => config('services.airalo.client_secret'),
                    'grant_type' => 'client_credentials',
                ])
                ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))
                ->json();

            $token = $response['data']['access_token'] ?? null;
            if (! $token) {
                throw new EsimProviderException('Airalo token request returned no access_token.');
            }

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        // Bounded so a slow/dead provider can't hang the worker + session lock.
        // 3s connect, 8s total for reads; orderBundle() overrides to 15s.
        return Http::baseUrl($this->baseUrl())
            ->timeout(8)->connectTimeout(3)
            ->withToken($this->accessToken())
            ->acceptJson();
    }

    /** Raw /packages data (country → operators → packages). Mapped on sync. */
    public function getCatalogue(): array
    {
        return $this->client()->get('/packages', ['limit' => 500])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json('data') ?? [];
    }

    /** Exchange rates (used for NGN display in CurrencyService). */
    public function getExchangeRates(): array
    {
        return $this->client()->get('/exchange-rates')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        // Purchase call — longer 15s ceiling for a real order confirmation.
        return $this->client()->timeout(15)->asMultipart()->post('/orders', [
            'package_id' => $planId,
            'quantity' => $qty,
            'type' => 'sim',
        ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/sims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/sims/{$iccid}/usage")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        // Airalo has no self-serve API revoke; refunds go through partner
        // support. ProviderRouter refunds via the wallet, not this path.
        throw new EsimProviderException('Airalo does not support API-side revoke; handle refunds via partner support.');
    }

    public function getBalance(): float
    {
        // Airalo exposes partner credit in the dashboard, not a documented
        // balance endpoint; Airalo is not in the low-balance-alert set.
        return 0.0;
    }
}
