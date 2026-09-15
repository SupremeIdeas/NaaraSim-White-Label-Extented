<?php

namespace App\Services\SMS;

use App\Exceptions\SmsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 (placeholder tier) — Sonetel permanent numbers, small-business
 * tier. Native call-forwarding (maps onto the Numbers Call Forwarding surface).
 * Adapter built + shipped enabled=false; activate once an account exists.
 * Implements NumberProviderInterface.
 *
 * Auth (owner audit, 2026-09-15 — verified live against Sonetel's own public
 * api-docs repo, github.com/Sonetel/sonetel-api-docs — NOT a guess): Sonetel
 * is OAuth2 password-grant, never a static API key. We exchange the account's
 * username+password (Basic-auth'd as the public client sonetel-api:sonetel-api)
 * for a bearer access_token at /SonetelAuth/oauth/token, cache it, and every
 * other call just asks token() for a still-good one — refreshed automatically
 * on a 401 (the docs disagree on the real lifetime: 24h in one section, 6
 * months in another, so we cache conservatively rather than trust either).
 *
 * Endpoints (same source): search availablephonenumber, buy/release via the
 * account's phonenumbersubscription resource — none of these are the generic
 * REST guesses this adapter shipped with before the audit.
 */
class SonetelService implements NumberProviderInterface
{
    private const TOKEN_CACHE_KEY = 'sonetel.oauth.access_token';

    // Conservative cache window — well under the shorter of the two lifetimes
    // the docs mention, so a call never fires on a token Sonetel already expired.
    private const TOKEN_TTL_SECONDS = 12 * 3600;

    private function configured(): bool
    {
        return filled(config('services.sonetel.username')) && filled(config('services.sonetel.password'));
    }

    private function guardConfigured(): void
    {
        if (! $this->configured()) {
            throw new SmsException('Sonetel is not configured.');
        }
    }

    private function accountId(): string
    {
        $id = (string) config('services.sonetel.account_id');
        if ($id === '') {
            throw new SmsException('Sonetel account ID is not configured.');
        }

        return $id;
    }

    /** A cached bearer token, live-fetched via OAuth2 password grant on first use. */
    private function token(): string
    {
        $this->guardConfigured();

        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, function () {
            $res = Http::asForm()->timeout(15)
                ->withBasicAuth('sonetel-api', 'sonetel-api')
                ->post(rtrim((string) config('services.sonetel.auth_url'), '/'), [
                    'grant_type' => 'password',
                    'username' => config('services.sonetel.username'),
                    'password' => config('services.sonetel.password'),
                ]);

            $token = (string) data_get($res->json(), 'access_token', '');
            if ($token === '') {
                throw new SmsException('Sonetel authentication failed: '.data_get($res->json(), 'error_description', $res->body()));
            }

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.sonetel.base_url'), '/'))
            ->withToken($this->token())->acceptJson()->timeout(15);
    }

    /**
     * One retry with a forced-fresh token if the cached one was revoked or
     * expired server-side before our own TTL caught up.
     */
    private function withFreshTokenOnAuthFailure(\Closure $call): Response
    {
        $res = $call($this->client());
        if ($res->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $res = $call($this->client());
        }

        return $res;
    }

    public function searchNumbers(string $country, array $options = []): array
    {
        $res = $this->withFreshTokenOnAuthFailure(
            fn (PendingRequest $client) => $client->get('/numberstocksummary/'.strtolower($country).'/availablephonenumber')
        );
        $json = $res->json();

        return collect(data_get($json, 'response', $json))->map(fn ($n) => [
            'number' => (string) data_get($n, 'phnum', data_get($n, 'number', '')),
            'locality' => (string) data_get($n, 'city', data_get($n, 'area', '')),
            'monthly_cost' => (float) data_get($n, 'monthly_fee', data_get($n, 'setup.monthly', 0)),
        ])->all();
    }

    public function buyNumber(string $country, array $options = []): array
    {
        $number = (string) ($options['number'] ?? '');
        if ($number === '') {
            throw new SmsException('Sonetel requires a specific number to purchase.');
        }

        $res = $this->withFreshTokenOnAuthFailure(
            fn (PendingRequest $client) => $client->post('/account/'.$this->accountId().'/phonenumbersubscription', [
                'phnum' => ltrim($number, '+'),
            ])
        );

        if (! $res->successful()) {
            throw new SmsException('Sonetel could not provision that number: '.data_get($res->json(), 'message', $res->body()));
        }

        return [
            'number' => $number,
            'provider_ref' => (string) data_get($res->json(), 'response.phnum', $number),
            'capabilities' => ['sms' => false, 'voice' => true],
        ];
    }

    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array
    {
        // Confirmed against the full official spec repo (owner audit,
        // 2026-09-15): Sonetel has no send-SMS resource at all — only
        // inbound-SMS-routing settings on a number. Voice/forwarding only.
        throw new SmsException('Sonetel does not support outbound SMS.');
    }

    public function outboundSmsCost(string $to): float
    {
        return 0.0;
    }

    public function releaseNumber(string $providerRef): void
    {
        $this->withFreshTokenOnAuthFailure(
            fn (PendingRequest $client) => $client->delete('/account/'.$this->accountId().'/phonenumbersubscription/'.$providerRef)
        );
    }

    public function monthlyCost(string $country): float
    {
        $numbers = $this->searchNumbers($country);

        return (float) ($numbers[0]['monthly_cost'] ?? 0);
    }
}
