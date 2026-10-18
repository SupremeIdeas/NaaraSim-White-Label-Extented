<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 1GLOBAL (Connect API) — a Full-eSIM provider for the Naara Connect line
 * (branded eSIM + voice + data, 200+ destinations, single-tap provisioning).
 *
 * Onboarding is partner-access (not self-serve), so this is key-gated: it stays
 * "Coming Soon" until credentials are saved, exactly like eSIM Go/Airalo were
 * before their keys landed. The Connect API uses an OAuth2 client-credentials
 * flow (token cached until just before expiry) then Bearer on every call.
 *
 * NOTE: the concrete paths below follow the documented Connect API shape
 * (docs.connect.1global.com). Confirm them against the sandbox once partner
 * access is granted — the CatalogueSync SyncStatus surfaces any 4xx so a wrong
 * path is visible, never silent. Money-safety: the catalogue cost is WHOLESALE
 * and PRIVATE; retail is always ours via the PricingEngine.
 */
class OneGlobalService implements EsimProviderInterface
{
    private const TOKEN_CACHE_KEY = 'oneglobal:access_token';

    private function baseUrl(): string
    {
        return rtrim(config('services.oneglobal.base_url'), '/');
    }

    /** Keycloak client-credentials token (valid ~5 min; cached 4). Documented at docs.connect.1global.com/docs/platform/authentication. */
    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(4), function () {
            $response = Http::timeout(8)->connectTimeout(3)->asForm()->acceptJson()
                ->post((string) config('services.oneglobal.token_url'), [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('services.oneglobal.client_id'),
                    'client_secret' => config('services.oneglobal.client_secret'),
                ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json();

            $token = $response['access_token'] ?? null;
            if (! $token) {
                throw new EsimProviderException('1GLOBAL token request returned no access_token.');
            }

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        // Bounded so a slow/dead provider can't hang the worker + session lock.
        // 3s connect, 8s total for reads; orderBundle() overrides to 15s.
        return Http::baseUrl($this->baseUrl())
            ->timeout(8)->connectTimeout(3)
            ->withToken($this->accessToken())
            ->acceptJson();
    }

    /** GET /v1/products -> [{id, name, description, period, period_unit, data:{value,units}, footprint[], activeCountries[], pricing, region}]. */
    public function getCatalogue(): array
    {
        $res = $this->client()->timeout(30)->get('/v1/products')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        return array_is_list($res) ? ['list' => $res] : $res;
    }

    /**
     * POST /v1/orders {input:{operationType:NEW_ESIM|TOPUP, customer, esim:{iccid}, subscriptions:[{product_id}]}} -> {id, status, matching_id, iccid,
     * lpa_url, qrcode_url, subscription_id}. A top-up passes the existing ICCID. The customer is a synthetic per-order record.
     */
    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $ref = 'naara-'.Str::uuid()->toString();
        $domain = (string) config('services.oneglobal.customer_email_domain', 'users.invalid');
        $res = $this->client()->timeout(15)->post('/v1/orders', ['input' => array_filter([
            'operationType' => $iccid ? 'TOPUP' : 'NEW_ESIM',
            'customer' => ['email' => "{$ref}@{$domain}", 'firstName' => 'Naara', 'lastName' => 'eSIM'],
            'esim' => $iccid ? ['iccid' => $iccid] : null,
            'subscriptions' => [['product_id' => $planId]],
        ])])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        return $res + ['orderReference' => (string) ($res['id'] ?? $ref), 'activationCode' => $res['lpa_url'] ?? null, 'qrCodeUrl' => $res['qrcode_url'] ?? null,
            'pending' => empty($res['lpa_url']) && empty($res['iccid'])];
    }

    /** GET /v1/sim/{iccid}. */
    public function getEsim(string $iccid): array
    {
        return $this->client()->get('/v1/sim/'.rawurlencode($iccid))->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    /** GET /v1/sim/{iccid}/subscriptions -> each {id, status, initialBalance, spentBalance, ...} in bytes. */
    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get('/v1/sim/'.rawurlencode($iccid).'/subscriptions')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    /** DELETE /v1/sim/{iccid}. */
    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->delete('/v1/sim/'.rawurlencode($iccid))->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        // The Connect API documents no wallet-balance endpoint (billing is invoiced).
        return 0.0;
    }
}
