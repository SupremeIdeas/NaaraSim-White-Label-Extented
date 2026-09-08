<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * eSIM Go — PRIMARY provider (blueprint Section 5.2). API v2.5 only.
 * Auth via X-API-Key on every request; sandbox toggled with `x-sandbox: on`.
 * The catalogue `price` field is the WHOLESALE cost (PRIVATE).
 */
class EsimGoService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        // Bounded so a slow/dead provider can't hang the worker + session lock.
        // 3s connect, 8s total for reads; orderBundle() overrides to 15s.
        return Http::baseUrl(rtrim(config('services.esimgo.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withHeaders(array_filter([
                'X-API-Key' => config('services.esimgo.api_key'),
                'x-sandbox' => config('services.esimgo.sandbox') ? 'on' : null,
            ]))
            ->acceptJson();
    }

    public function getCatalogue(): array
    {
        return $this->client()->get('/catalogue')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        // Purchase call — longer 15s ceiling for a real order confirmation.
        return $this->client()->timeout(15)->post('/orders', [
            'item' => $planId,
            'quantity' => $qty,
            'assign' => ! is_null($iccid),
            'iccids' => $iccid ? [$iccid] : [],
        ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/esims/{$iccid}/bundles/{$bundleName}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->delete("/esims/{$iccid}/bundles/{$bundleName}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        $org = $this->client()->get('/organisation')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        return (float) ($org['balance'] ?? 0);
    }
}
