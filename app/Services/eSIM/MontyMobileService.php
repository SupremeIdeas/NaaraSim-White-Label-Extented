<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Monty Mobile (RSP API) — a Full-eSIM provider for the Naara Connect line
 * (800+ direct MNO relationships, sub-90s consumer provisioning, full lifecycle:
 * provisioning, top-up, renewal, plan changes, voice + data).
 *
 * Onboarding is sales-led (request docs/sandbox via montymobile.com), so this is
 * key-gated: "Coming Soon" until credentials are saved. Auth is an API key sent
 * as a Bearer token on every RSP call.
 *
 * NOTE: the paths follow the documented RSP API shape; confirm against the
 * sandbox once partner access is granted (CatalogueSync SyncStatus surfaces any
 * 4xx). Money-safety: catalogue cost is WHOLESALE and PRIVATE; retail is ours.
 */
class MontyMobileService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.montymobile.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withToken(config('services.montymobile.api_key'))
            ->acceptJson();
    }

    public function getCatalogue(): array
    {
        return $this->client()->get('/rsp/v1/plans', ['pageSize' => 500])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        return $this->client()->timeout(15)->post('/rsp/v1/esims', array_filter([
            'planId' => $planId,
            'quantity' => $qty,
            'iccid' => $iccid,
            'externalRef' => 'naara-'.Str::uuid()->toString(),
        ]))->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/rsp/v1/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/rsp/v1/esims/{$iccid}/usage")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->delete("/rsp/v1/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        $b = $this->client()->get('/rsp/v1/account/balance')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        return (float) ($b['balance'] ?? $b['availableBalance'] ?? 0);
    }
}
