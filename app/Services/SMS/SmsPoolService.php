<?php

namespace App\Services\SMS;

use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 — SMSPool (SMS/OTP + rental), self-service tier. ~150 countries,
 * notably NON-VoIP numbers (a real differentiator for Naara Verify success). Ships
 * enabled=false until Frank onboards it. Implements the existing
 * SmsProviderInterface exactly — no new SDK contract.
 *
 * SMSPool's API is form-POST with the key in the body; responses are JSON.
 */
class SmsPoolService implements SmsProviderInterface
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.smspool.base_url'), '/'))
            ->asForm()->acceptJson()->timeout(15);
    }

    private function key(): string
    {
        $k = (string) config('services.smspool.api_key');
        if ($k === '') {
            throw new SmsException('SMSPool is not configured.');
        }

        return $k;
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        $json = $this->client()->post('/request/price', [
            'key' => $this->key(), 'country' => $country, 'service' => $service,
        ])->json();

        $price = (float) data_get($json, 'price', 0);
        if ($price <= 0) {
            throw new OutOfStockException("SMSPool has no stock for {$service} in {$country}.");
        }

        return $price;
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        // pool 0 = auto; max_price honoured so the router's margin guard holds.
        $json = $this->client()->post('/purchase/sms', array_filter([
            'key' => $this->key(), 'country' => $country, 'service' => $service,
            'max_price' => $options['max_price'] ?? null,
            'pricing_option' => 0,
        ], fn ($v) => $v !== null))->json();

        if ((int) data_get($json, 'success', 0) !== 1) {
            throw new OutOfStockException('SMSPool could not allocate a number: '.data_get($json, 'message', 'no stock'));
        }

        return [
            'provider_ref' => (string) data_get($json, 'order_id', data_get($json, 'orderid')),
            'number' => (string) data_get($json, 'phonenumber', data_get($json, 'number')),
        ];
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        $json = $this->client()->post('/purchase/rental', array_filter([
            'key' => $this->key(), 'country' => $country, 'service' => $service,
            'days' => $options['rental_time'] ?? 1,
        ], fn ($v) => $v !== null))->json();

        if ((int) data_get($json, 'success', 0) !== 1) {
            throw new OutOfStockException('SMSPool rental unavailable: '.data_get($json, 'message', 'no stock'));
        }

        return [
            'provider_ref' => (string) data_get($json, 'rental_id', data_get($json, 'id')),
            'number' => (string) data_get($json, 'phonenumber', data_get($json, 'number')),
        ];
    }

    /** SMSPool rentals are per-service, not an all-service catch — no full rent. */
    public function supportsFullRent(): bool
    {
        return false;
    }

    public function check(string $providerRef): array
    {
        $json = $this->client()->post('/sms/check', ['key' => $this->key(), 'orderid' => $providerRef])->json();
        $status = (int) data_get($json, 'status', 0);

        // SMSPool status: 3 = completed (code arrived), 6 = refunded/cancelled.
        return [
            'status' => match ($status) {
                3 => 'completed',
                6 => 'cancelled',
                default => 'waiting',
            },
            'code' => data_get($json, 'sms', data_get($json, 'code')),
        ];
    }

    public function finish(string $providerRef): void
    {
        $this->client()->post('/sms/resend', ['key' => $this->key(), 'orderid' => $providerRef]);
    }

    public function cancel(string $providerRef): void
    {
        $this->client()->post('/sms/cancel', ['key' => $this->key(), 'orderid' => $providerRef]);
    }

    public function balance(): float
    {
        $json = $this->client()->post('/request/balance', ['key' => $this->key()])->json();

        return (float) data_get($json, 'balance', 0);
    }
}
