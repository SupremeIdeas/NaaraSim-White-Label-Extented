<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 (placeholder tier) — Ubigi (Transatel) MVNO-backed data eSIMs.
 * Enterprise onboarding (account manager). Adapter built + shipped enabled=false;
 * activate once a partner account exists. Implements EsimProviderInterface.
 */
class UbigiService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        $key = (string) config('services.ubigi.api_key');
        if ($key === '') {
            throw new EsimProviderException('Ubigi is not configured (enterprise account required).');
        }

        return Http::baseUrl(rtrim((string) config('services.ubigi.base_url'), '/'))
            ->withToken($key)->acceptJson()->timeout(20);
    }

    public function getCatalogue(): array
    {
        return (array) data_get($this->client()->get('/catalog/plans')->json(), 'plans', []);
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $json = $this->client()->post('/esim/orders', array_filter([
            'planId' => $planId, 'quantity' => $qty, 'iccid' => $iccid,
        ], fn ($v) => $v !== null))->json();

        if (! data_get($json, 'id')) {
            throw new EsimProviderException('Ubigi order failed.');
        }

        return [
            'provider_ref' => (string) data_get($json, 'id'),
            'iccid' => (string) data_get($json, 'iccid', ''),
            'lpa' => (string) data_get($json, 'activationCode', ''),
        ];
    }

    public function getEsim(string $iccid): array
    {
        return (array) $this->client()->get("/esim/{$iccid}")->json();
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        $json = $this->client()->get("/esim/{$iccid}/usage")->json();

        return ['remaining_mb' => (int) data_get($json, 'remainingMb', 0), 'used_mb' => (int) data_get($json, 'usedMb', 0)];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return (array) $this->client()->delete("/esim/{$iccid}")->json();
    }

    public function getBalance(): float
    {
        return (float) data_get($this->client()->get('/account/balance')->json(), 'balance', 0);
    }
}
