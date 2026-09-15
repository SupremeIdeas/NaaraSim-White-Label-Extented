<?php

namespace App\Services\SMS;

use App\Exceptions\OutOfStockException;
use App\Support\NumberCatalogue;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

    /**
     * Provider-side English country names (as the protocol's `getCountries`
     * reports them) that diverge from NaaraSim's own NumberCatalogue label —
     * normalized name => our canonical label, so the live-discovery match in
     * syncCatalogue() still lines up (owner audit, 2026-09-15).
     */
    private const COUNTRY_NAME_ALIASES = [
        'usa' => 'United States',
        'unitedstatesofamerica' => 'United States',
        'england' => 'United Kingdom',
        'greatbritain' => 'United Kingdom',
        'uk' => 'United Kingdom',
        'czechrepublic' => 'Czechia',
        'ivorycoast' => "Côte d'Ivoire",
        'uae' => 'United Arab Emirates',
        'drcongo' => 'Congo',
        'democraticrepublicofcongo' => 'Congo',
        'republicofcongo' => 'Congo',
    ];

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

    /**
     * Country name → provider identifier. Checks the LIVE-DISCOVERED map
     * (syncCatalogue(), name-matched against the provider's own API, never
     * guessed) first, then the manual operator-filled config map, then
     * passes the raw slug through unchanged — which fails safely via
     * priceFor()'s stock check rather than silently hitting the wrong
     * country (owner audit, 2026-09-15).
     */
    private function country(string $country): string
    {
        $key = strtolower($country);
        $discovered = NumberCatalogue::providerCountryMap($this->providerKey());
        if (isset($discovered[$key])) {
            return $discovered[$key];
        }

        $map = (array) $this->cfg('country_map', []);

        return (string) ($map[$key] ?? $country);
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

    /**
     * Live-discover this provider's country id table via the protocol's
     * `getCountries` action (SMS-Activate-standard: `{id: {id, eng, ...}}`)
     * and name-match it against NaaraSim's own NumberCatalogue — accurate,
     * since it is sourced from the provider itself rather than a guessed
     * numeric table, and safe to re-run at any time. A country the provider
     * has that we don't yet know about is returned too, so the catalogue
     * sync can extend NumberCatalogue with it (owner audit, 2026-09-15).
     * Fails soft (empty arrays) if the action doesn't exist or errors — this
     * NEVER blocks routing, which still falls back to the manual config map.
     *
     * @return array{countries: array<string, string>, country_map: array<string, string>}
     */
    public function syncCatalogue(): array
    {
        $empty = ['countries' => [], 'country_map' => []];
        if (! $this->configured()) {
            return $empty;
        }

        try {
            $data = $this->send('getCountries')->throw()->json();
        } catch (\Throwable) {
            return $empty;
        }
        if (! is_array($data)) {
            return $empty;
        }

        $base = NumberCatalogue::baseCountries();
        $byNormalizedLabel = [];
        foreach ($base as $slug => $label) {
            $byNormalizedLabel[self::normalizeName($label)] = $slug;
        }

        $countries = [];
        $countryMap = [];
        foreach ($data as $row) {
            if (! is_array($row) || ! isset($row['id']) || empty($row['eng']) || ! is_string($row['eng'])) {
                continue;
            }
            $id = (string) $row['id'];
            $engName = (string) $row['eng'];
            $aliased = self::COUNTRY_NAME_ALIASES[self::normalizeName($engName)] ?? $engName;
            $norm = self::normalizeName($aliased);

            if (isset($byNormalizedLabel[$norm])) {
                $countryMap[$byNormalizedLabel[$norm]] = $id;

                continue;
            }

            // Not one of ours yet — extend the catalogue with it too.
            $slug = Str::slug($engName, '');
            if ($slug === '' || isset($base[$slug]) || isset($countries[$slug])) {
                continue;
            }
            $countries[$slug] = $engName;
            $countryMap[$slug] = $id;
        }

        return ['countries' => $countries, 'country_map' => $countryMap];
    }

    /** Lowercase, ASCII-folded, alnum-only — so accents/punctuation/spacing never break a name match. */
    private static function normalizeName(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($name)));
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
