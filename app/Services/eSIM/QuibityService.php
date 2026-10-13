<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Quibity / eSIM.sm — TERTIARY provider (blueprint Section 5.4).
 * Bearer auth; sandbox via `x-sandbox: on`. The plans `price` field is the
 * WHOLESALE cost (PRIVATE). No minimum-price guard, but MarginGuard still
 * applies as the universal floor.
 */
class QuibityService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.quibity.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withToken(config('services.quibity.api_key'))
            ->withHeaders(array_filter([
                'x-sandbox' => config('services.quibity.sandbox') ? 'on' : null,
            ]))
            ->acceptJson();
    }

    /**
     * Quibity / eSIM.sm Partner API, per its public Postman docs (https://docs.esim.sm, base https://esim.sm/api/reseller/v1): the plans are listed per country
     * (`GET /country?id=XX`), there is no flat plan list, so the whole catalogue is every country's plans de-duplicated by plan id (regional plans repeat).
     * Verified against the live account on 2026-10-13: the earlier `/plans`, `/orders` and `/esims/{iccid}` paths do not exist.
     *
     * @return array{plans: list<array<string,mixed>>}
     */
    public function getCatalogue(): array
    {
        $fail = fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e);
        $countries = $this->client()->timeout(30)->get('/country', ['hl' => 'en', 'currency' => 'usd'])->throw($fail)->json('data') ?? [];
        $ids = array_values(array_filter(array_map(fn ($c) => $c['id'] ?? null, (array) $countries)));
        $plans = [];
        foreach (array_chunk($ids, 10) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($id) => $pool->as($id)->baseUrl(rtrim(config('services.quibity.base_url'), '/'))->timeout(25)->withToken(config('services.quibity.api_key'))
                    ->withHeaders(array_filter(['x-sandbox' => config('services.quibity.sandbox') ? 'on' : null]))->acceptJson()->get('/country', ['id' => $id, 'hl' => 'en', 'currency' => 'usd']),
                $chunk,
            ));
            foreach ($responses as $id => $res) {
                if ($res instanceof \Throwable || ! $res->successful()) {
                    continue;   // one country failing never loses the rest of the catalogue
                }
                foreach ((array) $res->json('data.plans') as $p) {
                    if (isset($p['id'])) {
                        $plans[(string) $p['id']] = $p;   // keyed by id: regional plans come back under every country they cover
                    }
                }
            }
        }

        return ['plans' => array_values($plans)];
    }

    /**
     * Buy one eSIM. The answer carries the new eSIM under `data.esim[0]` (iccid, lpaCode) and the price actually charged under `data.price`. `pending: true` means it
     * is not ready yet, which cannot be delivered to a waiting buyer, so it throws and the router fails over and refunds.
     */
    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $fail = fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e);
        if ($iccid) {
            $res = $this->client()->timeout(30)->post('/esim/top-up', ['iccid' => $iccid, 'refill' => $planId])->throw($fail)->json() ?? [];
        } else {
            $res = $this->client()->timeout(30)->post('/esim/purchase', ['plan_id' => (int) $planId, 'quantity' => max(1, min(10, $qty))])->throw($fail)->json() ?? [];
        }
        if (empty($res['success']) || ! empty($res['pending'])) {
            throw new EsimProviderException('Quibity did not complete the order: '.(! empty($res['pending']) ? 'the eSIM is still pending' : ($res['error'] ?? $res['message'] ?? 'no confirmation')));
        }
        $esim = (array) ($res['data']['esim'][0] ?? []);

        return $res + array_filter(['iccid' => $esim['iccid'] ?? $iccid, 'lpa' => $esim['lpaCode'] ?? null, 'esims' => $esim !== [] ? [$esim] : null, 'charged_usd' => $res['data']['price'] ?? null], fn ($v) => $v !== null);
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get('/esim', ['iccid' => $iccid])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json('data') ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->getEsim($iccid);   // usage is part of the eSIM record: mbTotal, mbUsed, status
    }

    /** Refund an eSIM that has not been installed (Quibity charges a small fee per refund). */
    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->post('/esim/refund', ['iccid' => $iccid])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        // The partner API has no balance endpoint (docs.esim.sm lists country, plan, params, esim list/info/purchase/top-up/refund only): the balance lives in the
        // Reseller Dashboard. Returning 0.0 keeps Quibity out of the low-balance alert set, as before.
        return 0.0;
    }
}
