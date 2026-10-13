<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * eSIM Go — PRIMARY provider (blueprint Section 5.2). API v2.5 only.
 * Auth via X-API-Key on every request; sandbox toggled with `x-sandbox: on`.
 * The catalogue `price` field is the WHOLESALE cost (PRIVATE).
 */
class EsimGoService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        // Bounded so a slow/dead provider can't hang the worker + session lock.
        // 3s connect, 8s total for reads; orderBundle() overrides to 15s.
        return Http::baseUrl(rtrim(config('services.esimgo.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withHeaders(array_filter([
                'X-API-Key' => config('services.esimgo.api_key'),
                'x-sandbox' => config('services.esimgo.sandbox') ? 'on' : null,
            ]))
            ->acceptJson();
    }

    /** Catalogue rows per request. The API caps nothing below its 5,000+ bundles, but a big page needs a longer timeout; 1000 keeps a full sync to a handful of calls (rate limit: 20 burst, 100 per minute). */
    private const CATALOGUE_PAGE = 1000;

    /**
     * The whole catalogue. The API pages its answer (default 50 of 5,000+ bundles), so a single call would silently import a fraction of the catalogue; this walks
     * every page and returns one `bundles` list (found 2026-10-13 against the live sandbox: 5,434 bundles over 109 pages of 50).
     */
    public function getCatalogue(): array
    {
        $fail = fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e);
        $bundles = [];
        $pages = 1;
        for ($page = 1; $page <= $pages && $page <= 50; $page++) {
            $res = $this->client()->timeout(30)->get('/catalogue', ['page' => $page, 'perPage' => self::CATALOGUE_PAGE])->throw($fail)->json() ?? [];
            $bundles = array_merge($bundles, (array) ($res['bundles'] ?? []));
            $pages = max(1, (int) ($res['pageCount'] ?? 1));
        }

        return ['bundles' => $bundles, 'pageCount' => $pages, 'rows' => count($bundles)];
    }

    /**
     * Buy a bundle and hand back what the checkout needs: the order, plus the new eSIM's ICCID and SM-DP+ details so the buyer gets a working QR / LPA string.
     * v2.5 wants `type: transaction` and an `Order` list (the old flat body was rejected with "Order must contain Order Items"). `assign: true` puts the bundle on an eSIM
     * (a fresh one, or the given ICCID for a top-up); without it the bundle only lands in inventory and nobody gets an eSIM. A response that is not `completed`, or
     * an order whose eSIM details cannot be fetched, throws so the router fails over and the buyer is refunded (never a paid order with no eSIM).
     */
    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $fail = fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e);
        $item = ['type' => 'bundle', 'quantity' => $qty, 'item' => $planId, 'allowReassign' => false] + ($iccid ? ['iccids' => [$iccid]] : []);
        $order = $this->client()->timeout(30)->post('/orders', ['type' => 'transaction', 'assign' => true, 'Order' => [$item]])->throw($fail)->json() ?? [];

        $status = strtolower((string) ($order['status'] ?? ''));
        if (($order['orderReference'] ?? '') === '' || ($status !== '' && $status !== 'completed')) {
            throw new EsimProviderException('eSIM Go did not complete the order: '.($order['statusMessage'] ?? $order['message'] ?? ($status ?: 'no order reference')));
        }

        // New eSIM: read its ICCID and SM-DP+ details from the order's assignments (a top-up already knows its ICCID).
        $esim = $iccid ? ['iccid' => $iccid] : $this->assignment((string) $order['orderReference']);

        return $order + array_filter([
            'iccid' => $esim['iccid'] ?? null,
            'matching_id' => $esim['matchingId'] ?? null,
            'smdp_address' => $esim['rspUrl'] ?? $esim['smdpAddress'] ?? null,
        ], fn ($v) => $v !== null && $v !== '') + ['esims' => [$esim]];
    }

    /**
     * The eSIM(s) an order created. Shape per eSIM Go v2.5 (`GET /esims/assignments?reference=`, JSON or CSV with iccid, matchingId, rspUrl); tolerant of a bare list, an
     * `esims` wrapper or CSV. Retried briefly because the assignment can trail the order by a moment. NOT yet exercised against a funded account: watch the first live order.
     *
     * @return array{iccid?:string, matchingId?:string, rspUrl?:string}
     */
    private function assignment(string $reference): array
    {
        $last = 'no eSIM was assigned';
        for ($try = 1; $try <= 3; $try++) {
            try {
                $res = $this->client()->timeout(20)->get('/esims/assignments', ['reference' => $reference]);
                if ($res->successful()) {
                    $json = $res->json();
                    $rows = is_array($json) ? (array_is_list($json) ? $json : (array) ($json['esims'] ?? $json['assignments'] ?? [$json])) : $this->csvRows($res->body());
                    $row = (array) ($rows[0] ?? []);
                    if (filled($row['iccid'] ?? null)) {
                        return $row;
                    }
                }
                $last = 'HTTP '.$res->status();
            } catch (\Throwable $e) {
                $last = $e->getMessage();
            }
            usleep(700000);
        }

        throw new EsimProviderException("eSIM Go order {$reference} completed but its eSIM could not be read ({$last}). Check the order in the eSIM Go portal.");
    }

    /** @return list<array<string,string>> */
    private function csvRows(string $csv): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $csv) ?: [])));
        if (count($lines) < 2) {
            return [];
        }
        $head = str_getcsv(array_shift($lines));

        return array_map(fn ($l) => array_combine($head, array_pad(str_getcsv($l), count($head), '')) ?: [], $lines);
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/esims/{$iccid}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/esims/{$iccid}/bundles/{$bundleName}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->delete("/esims/{$iccid}/bundles/{$bundleName}")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        $org = $this->client()->get('/organisation')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        // v2.5 answers {"organisations": [{... "balance": 0 ...}]}; the older flat shape is still read so a fake or an older API keeps working.
        return (float) ($org['organisations'][0]['balance'] ?? $org['balance'] ?? 0);
    }
}
