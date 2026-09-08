<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 1GLOBAL (Connect API) — a Full-eSIM provider for the Naara Connect line
 * (branded eSIM + voice + data, 200+ destinations, single-tap provisioning).
 *
 * Onboarding is partner-access (not self-serve), so this is key-gated: it stays
 * "Coming Soon" until credentials are saved, exactly like eSIM Go/Airalo were
 * before their keys landed. The Connect API uses an OAuth2 client-credentials
 * flow (token cached until just before expiry) then Bearer on every call.
 *
 * NOTE: the concrete paths below follow the documented Connect API shape
 * (docs.connect.1global.com). Confirm them against the sandbox once partner
 * access is granted — the CatalogueSync SyncStatus surfaces any 4xx so a wrong
 * path is visible, never silent. Money-safety: the catalogue cost is WHOLESALE
 * and PRIVATE; retail is always ours via the PricingEngine.
 */
class OneGlobalService implements EsimProviderInterface
{
    private const TOKEN_CACHE_KEY = 'oneglobal:access_token';

    private function baseUrl(): string
    {
        return rtrim(config('services.oneglobal.base_url'), '/');
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            $response = Http::timeout(8)->connectTimeout(3)->asForm()->acceptJson()
                ->post($this->baseUrl().'/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('services.oneglobal.client_id'),
                    'client_secret' => config('services.oneglobal.client_secret'),
                ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json();

            $token = $response['access_token'] ?? null;
            if (! $token) {
                throw new EsimProviderException('1GLOBAL token request returned no access_token.');
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

    public function getCatalogue(): array
    {
        return $this->client()->get('/plans', ['limit' => 500])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        // Purchase call — longer 15s ceiling for a real order confirmation.
        return $this->client()->timeout(15)->post('/esims', array_filter([
            'planId' => $planId,
            'quantity' => $qty,
            'iccid' => $iccid,
            'reference' => 'naara-'.Str::uuid()->toString(),
        ]))->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/esims/{$iccid}/usage")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->delete("/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        $b = $this->client()->get('/account/balance')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        return (float) ($b['balance'] ?? $b['available'] ?? 0);
    }
}
