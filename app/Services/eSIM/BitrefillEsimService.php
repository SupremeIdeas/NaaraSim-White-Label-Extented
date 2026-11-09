<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Bitrefill eSIMs (data-lane supplier). Same account and key as Bitrefill gift cards (services.bitrefill). Docs: /products/esims, POST /esims, GET /esims/invoice/{id},
 * GET /orders/{id}, GET /esims/{iccid}. A plan id is "<product_id>|<package value>" because a Bitrefill eSIM package is identified by its string value
 * ("3GB, 30 Days"), not an id of its own. The package `price` is what Bitrefill charges our balance, i.e. our WHOLESALE cost (PRIVATE, never shown); retail is ours via
 * PricingEngine. Bundles stack: a top-up passes the existing ICCID as `esim_id` and needs the SAME product.
 */
class BitrefillEsimService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        $key = (string) config('services.bitrefill.api_key');
        $http = Http::baseUrl(rtrim((string) config('services.bitrefill.base_url', 'https://api.bitrefill.com/v2'), '/'))->acceptJson()->timeout(20)->connectTimeout(4);

        return $key !== '' ? $http->withToken($key) : $http->withBasicAuth((string) config('services.bitrefill.api_id'), (string) config('services.bitrefill.api_secret'));
    }

    private function fail(string $what): \Closure
    {
        return fn ($r, $e) => throw new EsimProviderException("Bitrefill {$what}: ".$e->getMessage(), previous: $e);
    }

    /** All eSIM products with their bundles. Returns ['list' => [...]] like the other providers' paged catalogues. */
    public function getCatalogue(): array
    {
        $all = [];
        $start = 0;
        do {
            $res = $this->client()->get('/products/esims', ['limit' => 50, 'start' => $start])->throw($this->fail('catalogue'))->json();
            $rows = (array) ($res['data'] ?? []);
            $all = array_merge($all, $rows);
            $start += count($rows);
            $total = (int) data_get($res, 'meta.total', count($all));
        } while ($rows !== [] && $start < $total && $start < 2000);

        return ['list' => $all];
    }

    /**
     * Buy a bundle ($planId = "<product_id>|<value>"), or top up an existing eSIM when $iccid is given. Pays from the Bitrefill balance (auto_pay) and reads the order back
     * for the activation code, waiting a short bounded time. A purchase still in flight is returned as pending (never retried, which could deliver twice).
     */
    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        [$productId, $value] = array_pad(explode('|', $planId, 2), 2, '');
        if ($productId === '' || $value === '') {
            throw new EsimProviderException('Bitrefill plan id is malformed.');
        }

        $line = array_filter(['product_id' => $productId, 'value' => $value, 'quantity' => max(1, $qty), 'esim_id' => $iccid], fn ($v) => $v !== null && $v !== '');
        $res = $this->client()->timeout(25)->post('/esims', ['products' => [$line], 'payment_method' => 'balance', 'auto_pay' => true])
            ->throw($this->fail('purchase'))->json();
        $invoice = (array) ($res['data'] ?? $res);
        $invoiceId = (string) ($invoice['id'] ?? '');

        $tries = (int) config('services.bitrefill.poll_tries', 8);
        for ($i = 0; $i < max(1, $tries); $i++) {
            $status = strtolower((string) ($invoice['status'] ?? ''));
            if (in_array($status, ['denied', 'payment_error', 'blocked'], true)) {
                throw new EsimProviderException('Bitrefill could not fulfil this eSIM ('.$status.').');
            }
            $order = (array) (($invoice['orders'][0] ?? []));
            if ($status === 'complete' || strtolower((string) ($order['status'] ?? '')) === 'delivered') {
                break;
            }
            if ($invoiceId === '' || $i >= $tries - 1) {
                break;
            }
            usleep((int) config('services.bitrefill.poll_wait_ms', 2000) * 1000);
            $invoice = (array) ($this->client()->get("/esims/invoice/{$invoiceId}")->throw($this->fail('invoice'))->json('data') ?? $invoice);
        }

        $order = (array) ($invoice['orders'][0] ?? []);
        $orderId = (string) ($order['id'] ?? '');
        $lpa = null;
        $delivered = strtolower((string) ($order['status'] ?? '')) === 'delivered';
        if ($orderId !== '' && $delivered) {
            $o = (array) ($this->client()->get("/orders/{$orderId}")->throw($this->fail('order'))->json('data') ?? []);
            $lpa = data_get($o, 'redemption_info.barcode_value') ?: data_get($o, 'redemption_info.code');
        }

        $iccidOut = $iccid ?: ($lpa ? $this->iccidFor((string) $lpa) : null);

        return array_filter([
            'invoice' => $invoice, 'id' => $invoiceId, 'orderReference' => $invoiceId, 'order_id' => $orderId,
            'lpa' => $lpa, 'iccid' => $iccidOut, 'charged_usd' => data_get($invoice, 'payment.price'),
            'pending' => ! $delivered,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Find the ICCID of the eSIM whose activation QR matches (Bitrefill lists eSIMs by ICCID with their barcode_value). Best effort. */
    private function iccidFor(string $lpa): ?string
    {
        try {
            foreach ((array) $this->client()->get('/esims', ['limit' => 50])->json('data', []) as $e) {
                if (($e['barcode_value'] ?? null) === $lpa) {
                    return (string) $e['id'];
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    public function getEsim(string $iccid): array
    {
        return (array) ($this->client()->get('/esims/'.rawurlencode($iccid))->throw($this->fail('eSIM'))->json('data') ?? []);
    }

    /** Remaining data per bundle from package_history (bytes remaining across active bundles). */
    public function getUsage(string $iccid, string $bundleName): array
    {
        $e = $this->getEsim($iccid);
        $active = collect((array) ($e['package_history'] ?? []))->where('status', 'active');
        $match = $bundleName !== '' ? $active->firstWhere('package_name', $bundleName) : null;
        $rows = $match ? collect([$match]) : $active;

        return $e + [
            'remaining_bytes' => (float) $rows->sum('remaining_quantity'),
            'initial_bytes' => (float) $rows->sum('initial_quantity'),
        ];
    }

    /** Bitrefill documents no revoke for eSIM bundles: a delivered eSIM is final (see /docs/refunds). */
    public function revoke(string $iccid, string $bundleName): array
    {
        throw new EsimProviderException('Bitrefill eSIMs cannot be revoked once delivered.');
    }

    public function getBalance(): float
    {
        $b = (array) ($this->client()->get('/accounts/balance')->throw($this->fail('balance'))->json('data') ?? []);

        return (float) ($b['balance'] ?? 0);
    }

    /** Parse "3GB, 30 Days" / "500MB, 7 Days" / "Unlimited, 1 Day" into [data_mb|null, days|null, unlimited]. @return array{0: ?int, 1: ?int, 2: bool} */
    public static function parseValue(string $v): array
    {
        $unlimited = (bool) preg_match('/unlimited/i', $v);
        $mb = null;
        if (preg_match('/([\d.]+)\s*(GB|MB|TB)/i', $v, $m)) {
            $n = (float) $m[1];
            $mb = (int) round(match (strtoupper($m[2])) { 'TB' => $n * 1024 * 1024, 'GB' => $n * 1024, default => $n });
        }
        $days = null;
        if (preg_match('/(\d+)\s*(day|week|month)/i', $v, $m)) {
            $days = (int) $m[1] * match (strtolower($m[2])) { 'week' => 7, 'month' => 30, default => 1 };
        }

        return [$unlimited ? null : $mb, $days, $unlimited];
    }

    public static function planId(string $productId, string $value): string
    {
        return $productId.'|'.$value;
    }

    public static function newReference(): string
    {
        return 'naara-'.Str::uuid()->toString();
    }
}
