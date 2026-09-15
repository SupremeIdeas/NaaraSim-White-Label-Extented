<?php

namespace App\Services\SMS;

use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use App\Support\NumberCatalogue;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * NAARA-BUILD-18 — OnlineSIM (SMS/OTP + rental), self-service tier. ~108 countries,
 * single-use and long-term rental in one API. Ships enabled=false. Implements the
 * existing SmsProviderInterface. OnlineSIM's API takes the key as a query param
 * and returns JSON with a `response` status field.
 *
 * Owner audit (2026-09-15) — verified against OnlineSIM's own Postman
 * collections (legacy onlinesim.ru + current v1.1): `country` is a NUMERIC
 * INTERNAL ID (e.g. 7=Russia, 86=China) — never the plain slug this adapter
 * shipped with before the audit, which would have silently targeted the wrong
 * country (or none) on every call. `service` is already the right shape (a
 * short slug like `whatsapp`), so only country needs resolving. See
 * syncCatalogue() for the live-discovery that fills the mapping — until it
 * runs for a given country, resolveCountry() fails safe (OutOfStockException,
 * same as "no stock") rather than guessing an ID that could hit someone else's
 * country.
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

    /**
     * Our slug -> OnlineSIM's numeric country ID. Confirmed required (not
     * optional like SMSPool's name-or-ID) — an unmapped country fails safe
     * as "no stock" rather than guessing.
     */
    private function resolveCountry(string $country): string
    {
        $map = NumberCatalogue::providerCountryMap('onlinesim');
        if (isset($map[$country])) {
            return $map[$country];
        }

        throw new OutOfStockException("OnlineSIM has no confirmed country id for {$country} yet.");
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        $json = $this->client()->get('/getTariffs.php', [
            'apikey' => $this->key(), 'country' => $this->resolveCountry($country), 'service' => $service,
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
            'apikey' => $this->key(), 'service' => $service, 'country' => $this->resolveCountry($country),
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
            'apikey' => $this->key(), 'service' => $service, 'country' => $this->resolveCountry($country),
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

        // OnlineSIM status values (confirmed against the official Postman
        // collections, both legacy and current v1.1 — 2026-09-15 owner
        // audit): TZ_NUM_WAIT/TZ_INPOOL = waiting, TZ_NUM_ANSWER = code
        // arrived, TZ_OVER_EMPTY = no code in time. `TZ_NUM_CANCEL`
        // (previously mapped here) does not appear in either official spec
        // and was dead code; `TZ_OVER_OK`'s real meaning isn't confirmed
        // either, so it now falls through to the safe default instead of
        // being mislabelled "cancelled".
        //
        // Mapped onto the shared OtpStatus vocabulary every OTP job reads —
        // PollSmsOtpJob only ever matches OtpStatus::RECEIVED, so the raw
        // provider-local string this returned before the audit ('completed')
        // was NEVER recognised: a successfully-delivered OnlineSIM code
        // silently ran out the 15-minute window and auto-refunded anyway.
        // Same class of bug as SMSPool's, fixed the same way.
        return match ($status) {
            'TZ_NUM_ANSWER' => ['status' => OtpStatus::RECEIVED, 'code' => (string) data_get($row, 'msg', '')],
            'TZ_OVER_EMPTY' => ['status' => OtpStatus::TIMEOUT, 'code' => null],
            default => ['status' => OtpStatus::PENDING, 'code' => null],
        };
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

    /**
     * Live-discover OnlineSIM's own numeric country IDs via getNumbersStats.php
     * and name-match them against NumberCatalogue (never guessed — mirrors
     * HeroSmsService::syncCatalogue()). Requires a configured key (unlike
     * SMSPool's retrieve_all, this endpoint is not public). Fails soft to an
     * empty map on any error or unexpected shape — resolveCountry() then keeps
     * failing safe as "no stock" rather than routing to the wrong country.
     *
     * @return array{countries: array<string, string>, country_map: array<string, string>}
     */
    public function syncCatalogue(): array
    {
        $empty = ['countries' => [], 'country_map' => []];

        try {
            $key = $this->key();
        } catch (\Throwable) {
            return $empty;
        }

        try {
            $data = $this->client()->get('/getNumbersStats.php', ['apikey' => $key])->throw()->json();
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
        foreach ($data as $id => $row) {
            if (! is_array($row) || empty($row['name']) || ! is_string($row['name'])) {
                continue;
            }
            $id = (string) $id;
            $name = (string) $row['name'];
            $norm = self::normalizeName($name);

            if (isset($byNormalizedLabel[$norm])) {
                $countryMap[$byNormalizedLabel[$norm]] = $id;

                continue;
            }

            $slug = Str::slug($name, '');
            if ($slug === '' || isset($base[$slug]) || isset($countries[$slug])) {
                continue;
            }
            $countries[$slug] = $name;
            $countryMap[$slug] = $id;
        }

        return ['countries' => $countries, 'country_map' => $countryMap];
    }

    /** Lowercase, ASCII-folded, alnum-only — so accents/punctuation/spacing never break a name match. */
    private static function normalizeName(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($name)));
    }
}
