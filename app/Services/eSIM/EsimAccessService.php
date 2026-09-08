<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 — eSIM Access (Redtea) data eSIMs, self-service tier. GSMA-
 * certified, ~100+ countries. Ships enabled=false. Implements the existing
 * EsimProviderInterface — key in the RT-AccessCode header, JSON REST.
 */
class EsimAccessService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        $key = (string) config('services.esimaccess.api_key');
        if ($key === '') {
            throw new EsimProviderException('eSIM Access is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.esimaccess.base_url'), '/'))
            ->withHeaders(['RT-AccessCode' => $key])->acceptJson()->timeout(20);
    }

    public function getCatalogue(): array
    {
        $json = $this->client()->post('/open/package/list', [])->json();

        return (array) data_get($json, 'obj.packageList', data_get($json, 'obj', []));
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $json = $this->client()->post('/open/esim/order', array_filter([
            'packageCode' => $planId, 'count' => $qty, 'iccid' => $iccid,
        ], fn ($v) => $v !== null))->json();

        if (! data_get($json, 'success', false)) {
            throw new EsimProviderException('eSIM Access order failed: '.data_get($json, 'errorMsg', 'unknown'));
        }

        $obj = (array) data_get($json, 'obj', []);

        return [
            'provider_ref' => (string) data_get($obj, 'orderNo'),
            'iccid' => (string) data_get($obj, 'iccid', ''),
            'lpa' => (string) data_get($obj, 'ac', data_get($obj, 'qrCode', '')),
            'qr_code_url' => (string) data_get($obj, 'qrCodeUrl', ''),
        ];
    }

    public function getEsim(string $iccid): array
    {
        return (array) data_get($this->client()->post('/open/esim/query', ['iccid' => $iccid])->json(), 'obj', []);
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        $obj = (array) data_get($this->client()->post('/open/esim/query', ['iccid' => $iccid])->json(), 'obj', []);

        return [
            'remaining_mb' => (int) round((float) data_get($obj, 'totalVolume', 0) / 1048576),
            'used_mb' => (int) round((float) data_get($obj, 'orderUsage', 0) / 1048576),
        ];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return (array) $this->client()->post('/open/esim/cancel', ['iccid' => $iccid])->json();
    }

    public function getBalance(): float
    {
        $json = $this->client()->post('/open/balance/query', [])->json();

        return (float) data_get($json, 'obj.balance', 0);
    }
}
