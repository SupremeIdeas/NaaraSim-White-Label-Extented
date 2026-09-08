<?php

namespace App\Services\SMS;

use App\Exceptions\OutOfStockException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HeroSMS — the official successor to SMS-Activate (which shut down permanently
 * 2025-12-29). HeroSMS keeps the SMS-Activate `handler_api.php` protocol, so
 * this is a rewire of the old dead backup, not a fresh integration. It is the
 * PRIMARY "full rent" provider in the OTP/rental lane: a rented number here can
 * receive SMS from ANY service (`service=full`), which is what powers Naara
 * Rent's "Any Service" mode.
 *
 * Protocol (SMS-Activate-compatible, GET on handler_api.php):
 *   getBalance                        → ACCESS_BALANCE:12.34
 *   getPrices&country=&service=        → JSON {country:{service:{cost,count}}}
 *   getNumber&service=&country=        → ACCESS_NUMBER:id:phone
 *   getStatus&id=                      → STATUS_WAIT_CODE | STATUS_OK:code | STATUS_CANCEL
 *   setStatus&id=&status=              → 1 ready · 3 retry · 6 finish · 8 cancel
 *   getRentNumber&service=&rent_time=  → JSON {status:success, phone:{id,number,endDate}}
 *
 * Key-gated: until HEROSMS_API_KEY is set it reports itself unavailable, so the
 * router stays cleanly within the lane. Country/service identifiers are mapped
 * through operator-fillable config maps (SMS-Activate uses numeric country IDs +
 * short service codes); the mapping is the go-live step, the protocol is wired.
 *
 * OPS (surface before go-live): HeroSMS funds via CRYPTO ONLY on our end.
 */
class HeroSmsService implements SmsProviderInterface
{
    /** SMS-Activate "full rent" sentinel — a number open to ANY service. */
    private const FULL_RENT_SERVICE = 'full';

    /** The config()/label key for this provider — overridden by VirtSmsService. */
    protected function providerKey(): string
    {
        return 'herosms';
    }

    protected function label(): string
    {
        return 'HeroSMS';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return config("services.{$this->providerKey()}.{$key}", $default);
    }

    protected function configured(): bool
    {
        return ! empty($this->cfg('api_key'));
    }

    private function guardConfigured(): void
    {
        if (! $this->configured()) {
            throw new OutOfStockException($this->label().' is not configured yet.');
        }
    }

    /**
     * One GET on handler_api.php with the api_key + action query the protocol
     * expects. Params are proper query args so the response is faked/asserted
     * against the query string.
     */
    private function send(string $action, array $params = [], int $timeout = 8): Response
    {
        // Bounded so a slow/dead provider can't hang the worker + session lock.
        // 3s connect, 8s total for reads; purchase actions pass 15s.
        return Http::timeout($timeout)->connectTimeout(3)->acceptJson()->get($this->cfg('base_url'), array_merge([
            'api_key' => $this->cfg('api_key'),
            'action' => $action,
        ], $params));
    }

    /** Country name → provider identifier (operator-mapped; passthrough default). */
    private function country(string $country): string
    {
        $map = (array) $this->cfg('country_map', []);

        return (string) ($map[strtolower($country)] ?? $country);
    }

    /** Service slug → provider code (operator-mapped; passthrough default). */
    private function service(string $service): string
    {
        if ($service === self::FULL_RENT_SERVICE) {
            return self::FULL_RENT_SERVICE;
        }
        $map = (array) $this->cfg('service_map', []);

        return (string) ($map[strtolower($service)] ?? $service);
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        $this->guardConfigured();

        $data = $this->send('getPrices', [
            'country' => $this->country($country),
            'service' => $this->service($service),
        ])->throw()->json() ?? [];

        $row = data_get($data, $this->country($country).'.'.$this->service($service), []);
        $cost = (float) ($row['cost'] ?? 0);
        $count = (int) ($row['count'] ?? 0);

        if ($cost <= 0 || $count <= 0) {
            throw new OutOfStockException($this->label()." out of stock for {$service} in {$country}.");
        }

        return $cost;
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        $this->guardConfigured();

        $body = $this->send('getNumber', array_filter([
            'service' => $this->service($service),
            'country' => $this->country($country),
            'maxPrice' => $options['max_price'] ?? null,
        ]), timeout: 15)->throw()->body(); // purchase call

        return $this->parseAccessNumber($body);
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        $this->guardConfigured();

        // NumberRequest::SERVICE_ANY maps to SMS-Activate "full rent": a number
        // that receives SMS from ANY service for its rental period.
        $rentService = $service === NumberRequest::SERVICE_ANY
            ? self::FULL_RENT_SERVICE
            : $this->service($service);

        $json = $this->send('getRentNumber', array_filter([
            'service' => $rentService,
            'country' => $this->country($country),
            'rent_time' => $options['rent_hours'] ?? null,
        ]), timeout: 15)->throw()->json() ?? []; // purchase call

        if (($json['status'] ?? null) !== 'success' || ! isset($json['phone'])) {
            throw new OutOfStockException($this->label()." has no rental for {$service} in {$country}.");
        }

        return [
            'provider_ref' => (string) ($json['phone']['id'] ?? ''),
            'number' => (string) ($json['phone']['number'] ?? ''),
            'cost' => (float) ($json['phone']['cost'] ?? $json['phone']['price'] ?? 0),
            'status' => OtpStatus::PENDING,
        ];
    }

    /** HeroSMS full rent (getRentNumber service=full) receives SMS from ANY service. */
    public function supportsFullRent(): bool
    {
        return $this->configured();
    }

    public function check(string $providerRef): array
    {
        if (! $this->configured()) {
            return ['status' => OtpStatus::PENDING, 'code' => null];
        }

        $body = $this->send('getStatus', ['id' => $providerRef])->throw()->body();

        if (str_starts_with($body, 'STATUS_OK:')) {
            return ['status' => OtpStatus::RECEIVED, 'code' => substr($body, strlen('STATUS_OK:'))];
        }
        if (str_starts_with($body, 'STATUS_CANCEL')) {
            return ['status' => OtpStatus::CANCELED, 'code' => null];
        }

        return ['status' => OtpStatus::PENDING, 'code' => null];
    }

    public function finish(string $providerRef): void
    {
        if ($this->configured()) {
            $this->send('setStatus', ['id' => $providerRef, 'status' => 6]);
        }
    }

    public function cancel(string $providerRef): void
    {
        if ($this->configured()) {
            $this->send('setStatus', ['id' => $providerRef, 'status' => 8]);
        }
    }

    public function balance(): float
    {
        if (! $this->configured()) {
            return 0.0;
        }

        $body = $this->send('getBalance')->throw()->body();

        return str_starts_with($body, 'ACCESS_BALANCE:')
            ? (float) substr($body, strlen('ACCESS_BALANCE:'))
            : 0.0;
    }

    /** Parse `ACCESS_NUMBER:id:phone` (or throw on a stock/error body). */
    private function parseAccessNumber(string $body): array
    {
        if (! str_starts_with($body, 'ACCESS_NUMBER:')) {
            throw new OutOfStockException($this->label().' could not buy a number: '.$body);
        }

        [, $id, $number] = array_pad(explode(':', $body, 3), 3, '');

        return [
            'provider_ref' => $id,
            'number' => $number,
            'cost' => 0.0, // settled from getPrices at quote time; provider bills on delivery
            'status' => OtpStatus::PENDING,
        ];
    }
}
