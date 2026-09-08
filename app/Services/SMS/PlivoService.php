<?php

namespace App\Services\SMS;

use App\Exceptions\SmsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 — Plivo (permanent numbers + rental), self-service tier.
 * ~60–70 countries, mature docs; dual-use for Naara Rent and Naara Line. Ships
 * enabled=false. Implements the existing NumberProviderInterface — Basic auth
 * (auth_id:auth_token), REST under /Account/{auth_id}/.
 */
class PlivoService implements NumberProviderInterface
{
    private function client(): PendingRequest
    {
        $authId = (string) config('services.plivo.auth_id');
        $token = (string) config('services.plivo.auth_token');
        if ($authId === '' || $token === '') {
            throw new SmsException('Plivo is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.plivo.base_url'), '/')."/Account/{$authId}")
            ->withBasicAuth($authId, $token)->acceptJson()->timeout(15);
    }

    public function searchNumbers(string $country, array $options = []): array
    {
        $json = $this->client()->get('/PhoneNumber/', array_filter([
            'country_iso' => strtoupper($country),
            'pattern' => $options['contains'] ?? $options['pattern'] ?? null,
            'type' => 'local',
        ], fn ($v) => $v !== null))->json();

        return collect(data_get($json, 'objects', []))->map(fn ($n) => [
            'number' => (string) data_get($n, 'number'),
            'locality' => (string) data_get($n, 'region', data_get($n, 'city', '')),
            'monthly_cost' => (float) data_get($n, 'monthly_rental_rate', 0),
        ])->all();
    }

    public function buyNumber(string $country, array $options = []): array
    {
        $number = (string) ($options['number'] ?? '');
        $json = $this->client()->post("/PhoneNumber/{$number}/", [])->json();

        if (! data_get($json, 'numbers') && ! data_get($json, 'status', null)) {
            throw new SmsException('Plivo could not provision that number.');
        }

        return [
            'number' => $number,
            'provider_ref' => (string) data_get($json, 'numbers.0.number', $number),
            'capabilities' => ['sms' => true, 'voice' => true],
        ];
    }

    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array
    {
        $json = $this->client()->post('/Message/', array_filter([
            'src' => $from, 'dst' => $to, 'text' => $body,
            'media_urls' => $mediaUrl ? [$mediaUrl] : null, 'type' => $mediaUrl ? 'mms' : 'sms',
        ], fn ($v) => $v !== null))->json();

        return ['provider_ref' => (string) data_get($json, 'message_uuid.0', ''), 'status' => 'sent'];
    }

    public function outboundSmsCost(string $to): float
    {
        // Plivo prices per destination; without a live rate card we return 0 and
        // let PricingEngine apply its floor. Real rates come from the pricing sync.
        return 0.0;
    }

    public function releaseNumber(string $providerRef): void
    {
        $this->client()->delete("/Number/{$providerRef}/");
    }

    public function monthlyCost(string $country): float
    {
        $json = $this->client()->get('/PhoneNumber/', ['country_iso' => strtoupper($country), 'type' => 'local'])->json();

        return (float) data_get($json, 'objects.0.monthly_rental_rate', 0);
    }
}
