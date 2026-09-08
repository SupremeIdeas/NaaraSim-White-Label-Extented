<?php

namespace App\Services\SMS;

use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 — OnlineSIM (SMS/OTP + rental), self-service tier. ~108 countries,
 * single-use and long-term rental in one API. Ships enabled=false. Implements the
 * existing SmsProviderInterface. OnlineSIM's API takes the key as a query param
 * and returns JSON with a `response` status field.
 */
class OnlineSimService implements SmsProviderInterface
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.onlinesim.base_url'), '/'))
            ->acceptJson()->timeout(15);
    }

    private function key(): string
    {
        $k = (string) config('services.onlinesim.api_key');
        if ($k === '') {
            throw new SmsException('OnlineSIM is not configured.');
        }

        return $k;
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        $json = $this->client()->get('/getTariffs.php', [
            'apikey' => $this->key(), 'country' => $country, 'service' => $service,
        ])->json();

        $price = (float) data_get($json, "services.{$service}.price", data_get($json, 'price', 0));
        if ($price <= 0) {
            throw new OutOfStockException("OnlineSIM has no stock for {$service} in {$country}.");
        }

        return $price;
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        $json = $this->client()->get('/getNum.php', [
            'apikey' => $this->key(), 'service' => $service, 'country' => $country,
        ])->json();

        if (data_get($json, 'response') !== 1 && data_get($json, 'response') !== '1') {
            throw new OutOfStockException('OnlineSIM could not allocate a number: '.data_get($json, 'response'));
        }

        return [
            'provider_ref' => (string) data_get($json, 'tzid'),
            'number' => (string) data_get($json, 'number'),
        ];
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        $json = $this->client()->get('/rent/getRentNum.php', array_filter([
            'apikey' => $this->key(), 'service' => $service, 'country' => $country,
            'days' => $options['rental_time'] ?? 1,
        ], fn ($v) => $v !== null))->json();

        $tzid = data_get($json, 'item.tzid', data_get($json, 'tzid'));
        if (! $tzid) {
            throw new OutOfStockException('OnlineSIM rental unavailable.');
        }

        return [
            'provider_ref' => (string) $tzid,
            'number' => (string) data_get($json, 'item.number', data_get($json, 'number')),
        ];
    }

    public function supportsFullRent(): bool
    {
        return false;
    }

    public function check(string $providerRef): array
    {
        $json = $this->client()->get('/getState.php', ['apikey' => $this->key(), 'tzid' => $providerRef])->json();
        $row = is_array($json) ? ($json[0] ?? $json) : [];
        $status = (string) data_get($row, 'response', 'wait');

        return [
            'status' => match ($status) {
                'TZ_NUM_ANSWER' => 'completed',
                'TZ_OVER_OK', 'TZ_NUM_CANCEL' => 'cancelled',
                default => 'waiting',
            },
            'code' => data_get($row, 'msg'),
        ];
    }

    public function finish(string $providerRef): void
    {
        $this->client()->get('/setOperationOk.php', ['apikey' => $this->key(), 'tzid' => $providerRef]);
    }

    public function cancel(string $providerRef): void
    {
        $this->client()->get('/setOperationRevise.php', ['apikey' => $this->key(), 'tzid' => $providerRef]);
    }

    public function balance(): float
    {
        $json = $this->client()->get('/getBalance.php', ['apikey' => $this->key()])->json();

        return (float) data_get($json, 'balance', 0);
    }
}
