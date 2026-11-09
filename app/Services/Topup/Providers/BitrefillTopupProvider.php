<?php

namespace App\Services\Topup\Providers;

use App\Services\Topup\Concerns\LogsProviderCalls;
use App\Services\Topup\Contracts\TopupProviderInterface;
use App\Services\Topup\DTO\ProductDTO;
use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\DTO\RecipientInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bitrefill phone refills (airtime and data) through the same account and key as Bitrefill gift cards and eSIMs. Doc-confirmed (docs.bitrefill.com):
 * GET /products?country=XX&type=phone_refill, GET /check_phone_number, POST /invoices with phone_number (payment_method balance + auto_pay), GET /invoices/{id},
 * order statuses delivered | failed | refunded. A product has fixed `packages` (package_id "<product>&lt;&amp;&gt;<value>", `price` = what Bitrefill charges our balance) and/or a
 * `range` with `price_rate` (our cost per unit of the operator currency; package `price` and `price_rate` are in the PRODUCT currency, e.g. NGN 497 for a NGN 500 Airtel refill, not USD). Bitrefill has NO idempotency key on invoices, so an ambiguous network failure after the POST is
 * returned as PENDING (never retried, never refunded by us) and an admin checks it, exactly like every other pending top-up.
 */
class BitrefillTopupProvider implements TopupProviderInterface
{
    use LogsProviderCalls;

    public function code(): string
    {
        return 'bitrefill';
    }

    public function name(): string
    {
        return 'Bitrefill';
    }

    public function configured(): bool
    {
        return filled(config('services.bitrefill.api_key')) || (filled(config('services.bitrefill.api_id')) && filled(config('services.bitrefill.api_secret')));
    }

    public function newReference(): string
    {
        return 'nt-'.Str::lower((string) Str::ulid());
    }

    private function client(int $timeout = 30): PendingRequest
    {
        $http = Http::acceptJson()->baseUrl(rtrim((string) config('services.bitrefill.base_url', 'https://api.bitrefill.com/v2'), '/'))->timeout($timeout)->connectTimeout(4);
        $key = (string) config('services.bitrefill.api_key');

        return $key !== '' ? $http->withToken($key) : $http->withBasicAuth((string) config('services.bitrefill.api_id'), (string) config('services.bitrefill.api_secret'));
    }

    public function countries(): array
    {
        // Bitrefill has no country list endpoint for refills, so we ask it about every country the catalogue already knows (the shipped snapshot
        // plus whatever the other suppliers list). Without this the sync never visited a country and none of its ~650 refills were ever sold.
        return Cache::remember('topup.bitrefill.countries', 21600, fn () => \App\Models\TopupProduct::query()->distinct()->pluck('country_iso')->filter()->map('strtoupper')->values()->all());
    }

    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo
    {
        try {
            $r = $this->send($this->client(20), 'GET', '/check_phone_number', ['phone_number' => $e164Phone]);
        } catch (Throwable) {
            return null;
        }
        if (! $r->successful() || ! $r->json('operator_found')) {
            return null;                                   // several possible operators means we cannot tell: the customer picks
        }
        $d = (array) $r->json('data', []);
        if (! $d || array_is_list($d)) {
            return null;
        }

        return new RecipientInfo(strtoupper((string) ($d['country_code'] ?? $countryIso ?? '')), (string) ($d['name'] ?? ''), (string) ($d['id'] ?? ''));
    }

    public function listProducts(string $countryIso): array
    {
        $out = [];
        $start = 0;
        do {
            $r = $this->page(strtoupper($countryIso), $start)->throw();
            $rows = (array) $r->json('data', []);
            foreach ($rows as $p) {
                foreach ($this->map($p, strtoupper($countryIso)) as $dto) {
                    $out[] = $dto;
                }
            }
            $start += count($rows);
            $total = (int) $r->json('meta.total', count($rows));
        } while ($rows !== [] && $start < $total && $start < 500);

        return $out;
    }

    /** One product page. Bitrefill allows 60 product calls a minute: pace ourselves, and wait out a 429 once instead of dropping the country. */
    private function page(string $iso, int $start)
    {
        $go = fn () => $this->send($this->client(40), 'GET', '/products', ['country' => $iso, 'type' => 'phone_refill', 'limit' => 50, 'start' => $start]);
        if (! app()->runningUnitTests()) {
            usleep(1100000);
        }
        $r = $go();
        if ($r->status() === 429 && ! app()->runningUnitTests()) {
            sleep(30);
            $r = $go();
        }

        return $r;
    }

    /** @return list<ProductDTO> */
    private function map(array $p, string $iso): array
    {
        if (! ($p['in_stock'] ?? true) || empty($p['id'])) {
            return [];
        }
        // The `type=phone_refill` query is not honoured by the live API: it returns every brand in the country (supermarkets, bills, eSIMs, gift cards).
        // Only products categorised "refill" are airtime/data; anything else must never be sold as a top-up. (Verified against the live API 2026-11-08.)
        if (isset($p['categories']) && ! in_array('refill', (array) $p['categories'], true)) {
            return [];
        }
        $op = (string) ($p['name'] ?? $p['id']);
        $cur = strtoupper((string) ($p['currency'] ?? 'USD'));
        $out = [];
        foreach ((array) ($p['packages'] ?? []) as $pkg) {
            $value = $pkg['value'] ?? null;
            $price = (float) ($pkg['price'] ?? 0);
            $pid = (string) ($pkg['id'] ?? $pkg['package_id'] ?? '');
            if ($pid === '' || $price <= 0) {
                continue;
            }
            if (is_numeric($value)) {
                $out[] = new ProductDTO($pid, $iso, $op, 'airtime', $op.' '.$value.' '.$cur, $cur, isFixed: true, fixedAmount: (float) $value, operatorCode: (string) $p['id'],
                    costNative: $price, costCurrency: $cur, meta: ['product_id' => (string) $p['id']]);
            } else {
                [$mb, $days, $unl] = \App\Services\Topup\DataValue::parse((string) $value);
                $out[] = new ProductDTO($pid, $iso, $op, 'data', (string) $value, $cur, isFixed: true, fixedAmount: null, operatorCode: (string) $p['id'],
                    validityText: $days ? $days.' day'.($days > 1 ? 's' : '') : null, dataMb: $mb, dataUnlimited: $unl, costNative: $price, costCurrency: $cur, meta: ['product_id' => (string) $p['id']]);
            }
        }
        $range = (array) ($p['range'] ?? []);
        if (isset($range['min'], $range['max'], $range['price_rate']) && (float) $range['max'] > 0 && (float) $range['price_rate'] > 0) {
            $out[] = new ProductDTO((string) $p['id'], $iso, $op, 'airtime', $op.' airtime', $cur, isFixed: false, minAmount: (float) $range['min'], maxAmount: (float) $range['max'],
                operatorCode: (string) $p['id'], costRatio: (float) $range['price_rate'], costCurrency: $cur, meta: ['product_id' => (string) $p['id'], 'range' => true, 'step' => $range['step'] ?? null]);
        }

        return $out;
    }

    public function purchase(PurchaseRequest $req): PurchaseResult
    {
        $isRange = (bool) ($req->meta['range'] ?? ! $req->isFixed);
        $productId = (string) ($req->meta['product_id'] ?? (str_contains($req->providerSku, '<&>') ? explode('<&>', $req->providerSku)[0] : $req->providerSku));
        $line = ['product_id' => $productId, 'phone_number' => $req->phoneE164, 'quantity' => 1] + ($isRange ? ['value' => $req->amount] : ['package_id' => $req->providerSku]);

        try {
            $r = $this->send($this->client(60), 'POST', '/invoices', ['products' => [$line], 'payment_method' => 'balance', 'auto_pay' => true], $req->reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Network error: check with the supplier before retrying.');
        }
        if ($r->serverError()) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Supplier error '.$r->status().' (will requery).');
        }
        if (! $r->successful()) {
            $code = (string) $r->json('error_code', '');
            // A refusal BEFORE an invoice exists means nothing was bought: definitive, safe to fail over or refund.
            return new PurchaseResult(PurchaseResult::FAILED, $req->reference, providerCode: $code, message: (string) $r->json('message', 'Supplier refused the top-up.'), definitive: true, raw: (array) $r->json());
        }

        return $this->map_invoice((array) ($r->json('data') ?? $r->json()), $req->reference);
    }

    public function requery(string $reference, ?string $providerTxnId = null): PurchaseResult
    {
        if (! $providerTxnId) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'No supplier invoice id on file yet: check the supplier dashboard.');
        }
        try {
            $r = $this->send($this->client(30), 'GET', '/invoices/'.rawurlencode($providerTxnId), [], $reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Network error.');
        }
        if (! $r->successful()) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, $providerTxnId, message: 'Supplier has no answer yet.');
        }

        return $this->map_invoice((array) ($r->json('data') ?? $r->json()), $reference);
    }

    /**
     * Invoice complete + order delivered -> delivered. denied / payment_error / blocked, or a failed/refunded order -> failed (the supplier refunded us).
     * Anything else (unpaid, pending, payment_confirmed, created, unknown) -> pending. Unknown is never "failed".
     *
     * @param  array<string, mixed>  $inv
     */
    public function map_invoice(array $inv, string $ref): PurchaseResult
    {
        $status = strtolower((string) ($inv['status'] ?? ''));
        $order = (array) ($inv['orders'][0] ?? []);
        $ostatus = strtolower((string) ($order['status'] ?? ''));
        $id = isset($inv['id']) ? (string) $inv['id'] : null;
        $charged = isset($inv['payment']['price']) ? (float) $inv['payment']['price'] : null;

        $result = match (true) {
            $ostatus === 'delivered' => PurchaseResult::DELIVERED,
            in_array($ostatus, ['failed', 'refunded'], true), in_array($status, ['denied', 'payment_error', 'blocked'], true) => PurchaseResult::FAILED,
            default => PurchaseResult::PENDING,
        };

        return new PurchaseResult($result, $ref, $id, $ostatus ?: $status, (string) ($inv['message'] ?? ''), $charged, 'USD',
            definitive: $result === PurchaseResult::FAILED && in_array($status, ['denied', 'payment_error', 'blocked'], true), raw: $inv);
    }

    public function balance(): array
    {
        $r = $this->send($this->client(20), 'GET', '/accounts/balance')->throw();

        return ['amount' => (float) $r->json('data.balance', 0), 'currency' => (string) $r->json('data.currency', 'USD')];
    }

    /** The invoice webhook carries no signature in Bitrefill's docs, so it is NOT trusted: orders are confirmed by requery() (polled). */
    public function parseWebhook(Request $request): ?PurchaseResult
    {
        return null;
    }
}
