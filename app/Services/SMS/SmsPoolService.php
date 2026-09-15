<?php

namespace App\Services\SMS;

use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use App\Support\NumberCatalogue;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * NAARA-BUILD-18 — SMSPool (SMS/OTP + rental), self-service tier. ~150 countries,
 * notably NON-VoIP numbers (a real differentiator for Naara Verify success). Ships
 * enabled=false until Frank onboards it. Implements the existing
 * SmsProviderInterface exactly — no new SDK contract.
 *
 * SMSPool's API is form-POST with the key in the body; responses are JSON.
 *
 * Owner audit (2026-09-15) — verified live against api.smspool.net (not the
 * smspool.net/api path, which 404s): `country`/`service` accept a name OR an
 * ID (SMSPool's own validation errors say so), but the canonical/guaranteed
 * form is the numeric ID from /country/retrieve_all and /service/retrieve_all
 * — exact string-matching a "name" isn't documented behaviour we can rely on.
 * syncCatalogue() below live-discovers those IDs and name-matches them against
 * NumberCatalogue (same discipline as HeroSmsService::syncCatalogue()); when a
 * mapping exists we send the ID, otherwise we still pass the raw slug through
 * unchanged — never worse than before the audit, strictly better once synced.
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

    /** Our slug -> SMSPool's numeric country ID, when live-discovered; else the raw slug. */
    private function resolveCountry(string $country): string
    {
        return NumberCatalogue::providerCountryMap('smspool')[$country] ?? $country;
    }

    /** Our slug -> SMSPool's numeric service ID, when live-discovered; else the raw slug. */
    private function resolveService(string $service): string
    {
        return NumberCatalogue::providerServiceMap('smspool')[$service] ?? $service;
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        $json = $this->client()->post('/request/price', [
            'key' => $this->key(), 'country' => $this->resolveCountry($country), 'service' => $this->resolveService($service),
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
            'key' => $this->key(), 'country' => $this->resolveCountry($country), 'service' => $this->resolveService($service),
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
            'key' => $this->key(), 'country' => $this->resolveCountry($country), 'service' => $this->resolveService($service),
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

        // SMSPool status (confirmed live + against its own FAQ, 2026-09-15):
        // 1 = pending, 3 = complete (code arrived), 6 = refunded (no code in
        // time — money auto-returned, distinct from a request-side cancel).
        // Mapped onto the shared OtpStatus vocabulary every OTP job reads —
        // PollSmsOtpJob only ever matches OtpStatus::RECEIVED, so returning
        // a provider-local string here (as before the audit) meant a
        // successfully-delivered SMSPool code was NEVER recognised and the
        // order silently ran out the 15-minute window and auto-refunded
        // anyway. This was the real, live bug the audit was looking for.
        return match ($status) {
            3 => ['status' => OtpStatus::RECEIVED, 'code' => (string) data_get($json, 'sms', data_get($json, 'code', ''))],
            6 => ['status' => OtpStatus::CANCELED, 'code' => null],
            default => ['status' => OtpStatus::PENDING, 'code' => null],
        };
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

    /**
     * Live-discover SMSPool's own country + service IDs and name-match them
     * against NumberCatalogue (never guessed — mirrors HeroSmsService's
     * syncCatalogue()). Both retrieve_all endpoints work with no API key.
     *
     * @return array{countries: array<string, string>, country_map: array<string, string>, service_map: array<string, string>}
     */
    public function syncCatalogue(): array
    {
        $empty = ['countries' => [], 'country_map' => [], 'service_map' => []];

        try {
            $countryRows = $this->client()->get('/country/retrieve_all')->throw()->json();
            $serviceRows = $this->client()->get('/service/retrieve_all')->throw()->json();
        } catch (\Throwable) {
            return $empty;
        }
        if (! is_array($countryRows) || ! is_array($serviceRows)) {
            return $empty;
        }

        $baseCountries = NumberCatalogue::baseCountries();
        $byNormalizedCountry = [];
        foreach ($baseCountries as $slug => $label) {
            $byNormalizedCountry[self::normalizeName($label)] = $slug;
        }

        $countries = [];
        $countryMap = [];
        foreach ($countryRows as $row) {
            if (! is_array($row) || ! isset($row['ID']) || empty($row['name']) || ! is_string($row['name'])) {
                continue;
            }
            $id = (string) $row['ID'];
            $name = (string) $row['name'];
            $norm = self::normalizeName($name);

            if (isset($byNormalizedCountry[$norm])) {
                $countryMap[$byNormalizedCountry[$norm]] = $id;

                continue;
            }

            $slug = Str::slug($name, '');
            if ($slug === '' || isset($baseCountries[$slug]) || isset($countries[$slug])) {
                continue;
            }
            $countries[$slug] = $name;
            $countryMap[$slug] = $id;
        }

        $baseServices = NumberCatalogue::baseServices();
        $byNormalizedService = [];
        foreach ($baseServices as $slug => $label) {
            $byNormalizedService[self::normalizeName($label)] = $slug;
            $byNormalizedService[self::normalizeName($slug)] = $slug;
        }

        $serviceMap = [];
        foreach ($serviceRows as $row) {
            if (! is_array($row) || ! isset($row['ID']) || empty($row['name']) || ! is_string($row['name'])) {
                continue;
            }
            $norm = self::normalizeName((string) $row['name']);
            if (isset($byNormalizedService[$norm])) {
                $serviceMap[$byNormalizedService[$norm]] = (string) $row['ID'];
            }
        }

        return ['countries' => $countries, 'country_map' => $countryMap, 'service_map' => $serviceMap];
    }

    /** Lowercase, ASCII-folded, alnum-only — so accents/punctuation/spacing never break a name match. */
    private static function normalizeName(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($name)));
    }
}
