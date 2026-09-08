<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Quibity / eSIM.sm — TERTIARY provider (blueprint Section 5.4).
 * Bearer auth; sandbox via `x-sandbox: on`. The plans `price` field is the
 * WHOLESALE cost (PRIVATE). No minimum-price guard, but MarginGuard still
 * applies as the universal floor.
 */
class QuibityService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.quibity.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withToken(config('services.quibity.api_key'))
            ->withHeaders(array_filter([
                'x-sandbox' => config('services.quibity.sandbox') ? 'on' : null,
            ]))
            ->acceptJson();
    }

    public function getCatalogue(): array
    {
        return $this->client()->get('/plans')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        return $this->client()->timeout(15)->post('/orders', [
            'plan_id' => $planId,
            'quantity' => $qty,
            'customer_ref' => (string) Str::uuid(),
        ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->post("/esims/{$iccid}/cancel", [
            'reason' => 'refund',
        ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        // eSIM.sm reseller balance is not part of the low-balance-alert set;
        // return 0.0 until a documented balance endpoint is wired.
        return 0.0;
    }
}
