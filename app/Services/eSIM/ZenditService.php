<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Zendit — the provider behind the "Naara Connect" line (Full eSIMs: calls +
 * data). Unlike the data-only providers, a Zendit offer can carry voice minutes
 * and SMS, so its catalogue populates has_voice.
 *
 * REST v1, Bearer auth. Sandbox and production are different hosts (config picks
 * the host from the sandbox flag). Money-safety: an offer's real cost is
 * `cost.fixed / cost.currencyDivisor` — WHOLESALE, PRIVATE, never surfaced.
 * Zendit's suggested `price` block is ignored; retail is always ours via the
 * PricingEngine.
 */
class ZenditService implements EsimProviderInterface
{
    /** Zendit caps the offers page size at 200; we page until exhausted. */
    private const PAGE_SIZE = 200;

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.zendit.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withToken(config('services.zendit.api_key'))
            ->acceptJson();
    }

    /**
     * The full eSIM offer catalogue, paged through `_limit`/`_offset`. Returns a
     * flat list of raw offer objects under a `list` key so the sync mapper reads
     * it like the other providers.
     */
    public function getCatalogue(): array
    {
        $all = [];
        $offset = 0;

        do {
            $page = $this->client()->get('/esim/offers', [
                '_limit' => self::PAGE_SIZE,
                '_offset' => $offset,
            ])->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

            $rows = $page['list'] ?? [];
            $all = array_merge($all, $rows);

            $total = (int) ($page['total'] ?? count($all));
            $offset += self::PAGE_SIZE;
        } while (count($rows) === self::PAGE_SIZE && count($all) < $total);

        return ['list' => $all];
    }

    /**
     * Purchase an offer. Zendit fulfils asynchronously against an idempotent,
     * caller-supplied transactionId; we generate one per order (the money layer
     * already guards double-submits). We POST the purchase, then read it back so
     * the returned payload carries the activation confirmation (ICCID / LPA /
     * QR) when Zendit has it ready — matching what checkout expects.
     */
    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $transactionId = 'naara-'.Str::uuid()->toString();

        $this->client()->timeout(15)->post('/esim/purchases', array_filter([
            'transactionId' => $transactionId,
            'offerId' => $planId,
            'iccid' => $iccid,
        ]))->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e));

        // Read the purchase back for the activation confirmation. Flatten the
        // nested `confirmation` block to the top level so LpaActivation and the
        // checkout's data_get() lookups (iccid / activationCode / smdpAddress /
        // qrCodeUrl) find it regardless of nesting.
        $purchase = $this->client()->get("/esim/purchases/{$transactionId}")
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];

        $confirmation = $purchase['confirmation'] ?? [];

        return array_merge($purchase, $confirmation, [
            'transactionId' => $transactionId,
            'id' => $transactionId,
            'orderReference' => $transactionId,
        ]);
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/esim/{$iccid}/plans")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        // Zendit exposes remaining plans/usage per ICCID (no per-bundle path).
        return $this->client()->get("/esim/{$iccid}/plans")->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    /**
     * Refund/revoke an unstarted purchase. Here $iccid carries the Zendit
     * transactionId (the reference an order stores), which is how Zendit keys
     * its refund endpoint.
     */
    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->post("/esim/purchases/{$iccid}/refund")
            ->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
    }

    public function getBalance(): float
    {
        $b = $this->client()->get('/balance')->throw(fn ($r, $e) => throw new EsimProviderException($e->getMessage(), previous: $e))->json() ?? [];
        $divisor = (int) ($b['currencyDivisor'] ?? 1) ?: 1;

        return (float) ($b['availableBalance'] ?? 0) / $divisor;
    }
}
