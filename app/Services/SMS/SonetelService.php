<?php

namespace App\Services\SMS;

use App\Exceptions\SmsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 (placeholder tier) — Sonetel permanent numbers, small-business
 * tier. Native call-forwarding (maps onto the Numbers Call Forwarding surface).
 * Adapter built + shipped enabled=false; activate once an account exists.
 * Implements NumberProviderInterface — Bearer token, JSON REST.
 */
class SonetelService implements NumberProviderInterface
{
    private function client(): PendingRequest
    {
        $key = (string) config('services.sonetel.api_key');
        if ($key === '') {
            throw new SmsException('Sonetel is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.sonetel.base_url'), '/'))
            ->withToken($key)->acceptJson()->timeout(15);
    }

    public function searchNumbers(string $country, array $options = []): array
    {
        $json = $this->client()->get('/api/numbers/available', ['country' => strtoupper($country)])->json();

        return collect(data_get($json, 'response', $json))->map(fn ($n) => [
            'number' => (string) data_get($n, 'number'),
            'locality' => (string) data_get($n, 'area', ''),
            'monthly_cost' => (float) data_get($n, 'setup.monthly', 0),
        ])->all();
    }

    public function buyNumber(string $country, array $options = []): array
    {
        $json = $this->client()->post('/api/numbers/purchase', array_filter([
            'number' => $options['number'] ?? null, 'country' => strtoupper($country),
        ], fn ($v) => $v !== null))->json();

        return [
            'number' => (string) data_get($json, 'response.number', $options['number'] ?? ''),
            'provider_ref' => (string) data_get($json, 'response.number_id', ''),
            'capabilities' => ['sms' => false, 'voice' => true],
        ];
    }

    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array
    {
        // Sonetel numbers are voice/forwarding-first; SMS send is not offered.
        throw new SmsException('Sonetel does not support outbound SMS.');
    }

    public function outboundSmsCost(string $to): float
    {
        return 0.0;
    }

    public function releaseNumber(string $providerRef): void
    {
        $this->client()->delete("/api/numbers/{$providerRef}");
    }

    public function monthlyCost(string $country): float
    {
        $json = $this->client()->get('/api/numbers/available', ['country' => strtoupper($country)])->json();

        return (float) data_get($json, 'response.0.setup.monthly', 0);
    }
}
