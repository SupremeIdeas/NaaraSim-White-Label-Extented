<?php

namespace App\PayoutRails\Grey;

use App\Support\PayoutSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the Grey Business API (docs: grey-business-api.md / grey_openapi.json).
 * Bearer `gbsk_...` secret key; sandbox and production are separate hosts. Grey keys carry no
 * test/live marker, so the base URL is the only environment signal — {@see sandboxHost()}.
 */
class GreyClient
{
    public const SANDBOX = 'https://businessapi-sandbox.grey.co';

    public const PRODUCTION = 'https://businessapi.grey.co';

    public function baseUrl(): string
    {
        $set = trim((string) config('services.grey.base_url', ''));
        if ($set !== '') {
            return rtrim($set, '/');
        }

        // Nothing set: follow the payout mode, so live installs talk to production and everything else to the sandbox.
        return \App\Services\Payouts\Hardening\PayoutEnvGuard::mode() === 'live' ? self::PRODUCTION : self::SANDBOX;
    }

    public function secret(): string
    {
        return (string) config('services.grey.api_secret');
    }

    public function sandboxHost(): bool
    {
        return str_contains($this->baseUrl(), 'sandbox');
    }

    public function configured(): bool
    {
        return filled($this->secret());
    }

    /** A sandbox host must never serve live money, and a production host must never serve sandbox mode. */
    public function environmentConsistent(): bool
    {
        $live = \App\Services\Payouts\Hardening\PayoutEnvGuard::mode() === 'live';

        return $live ? ! $this->sandboxHost() : $this->sandboxHost();
    }

    private function http(?string $idempotencyKey = null): PendingRequest
    {
        $req = Http::connectTimeout(PayoutSettings::httpConnectTimeout())
            ->timeout(PayoutSettings::httpTimeout())
            ->withToken($this->secret())
            ->acceptJson()
            ->asJson();

        return $idempotencyKey ? $req->withHeaders(['X-Idempotency-Key' => $idempotencyKey]) : $req;
    }

    public function postPayout(array $body, string $idempotencyKey): Response
    {
        return $this->http($idempotencyKey)->post($this->baseUrl().'/v1/charge/payout', $body);
    }

    public function transactionsByClientReference(string $reference): Response
    {
        return $this->http()->get($this->baseUrl().'/api/v1/transactions', ['client_reference' => $reference, 'limit' => 5]);
    }

    public function balances(): Response
    {
        return $this->http()->get($this->baseUrl().'/v1/balances');
    }

    /** Quote a payout conversion (read-only). `destination_amount` is net of Grey's swap and withdrawal fees. */
    public function quoteWithdrawal(string $from, string $to, float $amount): Response
    {
        return $this->http()->post($this->baseUrl().'/v1/currency/rate', [
            'source_currency' => $from, 'destination_currency' => $to, 'source_amount' => $amount, 'transaction_type' => 'withdraw',
        ]);
    }

    /**
     * The payout methods Grey offers right now (cached an hour). Empty list when Grey can't be reached —
     * callers treat that as "can't confirm", never as "supported".
     *
     * @return list<array<string, mixed>>
     */
    public function payoutMethods(bool $fresh = false): array
    {
        $key = 'grey.payout_methods.'.md5($this->baseUrl());
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 3600, function () {
            $response = $this->http()->get($this->baseUrl().'/v1/payout-methods');
            if (! $response->successful()) {
                return [];
            }

            return array_values((array) data_get($response->json(), 'data.payout_methods', []));
        }) ?: [];
    }
}
