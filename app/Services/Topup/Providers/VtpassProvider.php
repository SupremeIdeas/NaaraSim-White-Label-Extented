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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * VTpass (Nigeria: MTN, Airtel, Glo, 9mobile). Built to the integration spec; the sandbox base URL, auth headers, request_id rule,
 * /pay, /service-variations, /requery and the 08011111111 / 201000000000 / 400000000000 sandbox numbers are doc-confirmed. UNVERIFIED
 * until a sandbox key is saved (see docs/topup/README.md): the live base URL, data serviceIDs, exact status strings and webhook auth.
 */
class VtpassProvider implements TopupProviderInterface
{
    use LogsProviderCalls;

    private const AIRTIME = ['MTN' => 'mtn', 'Airtel' => 'airtel', 'Glo' => 'glo', '9mobile' => 'etisalat'];

    private const DATA = ['MTN' => 'mtn-data', 'Airtel' => 'airtel-data', 'Glo' => 'glo-data', '9mobile' => 'etisalat-data'];

    public function code(): string
    {
        return 'vtpass';
    }

    public function name(): string
    {
        return 'VTpass';
    }

    public function configured(): bool
    {
        return filled(config('services.vtpass.api_key')) && filled(config('services.vtpass.secret_key'));
    }

    /** First 12 chars = YYYYMMDDHHmm in Africa/Lagos, then a unique suffix (VTpass rule). */
    public function newReference(): string
    {
        return now('Africa/Lagos')->format('YmdHi').strtoupper(Str::random(10));
    }

    private function base(): string
    {
        return rtrim((string) config('services.vtpass.base_url'), '/');
    }

    private function get(): PendingRequest
    {
        return Http::acceptJson()->baseUrl($this->base())->timeout(30)
            ->withHeaders(['api-key' => (string) config('services.vtpass.api_key'), 'public-key' => (string) config('services.vtpass.public_key')]);
    }

    private function post(): PendingRequest
    {
        return Http::acceptJson()->baseUrl($this->base())->timeout(60)
            ->withHeaders(['api-key' => (string) config('services.vtpass.api_key'), 'secret-key' => (string) config('services.vtpass.secret_key')]);
    }

    public function countries(): array
    {
        return ['NG'];
    }

    /** Customer picks the network (numbers are portable; prefix guessing is wrong often enough to cause mis-top-ups). */
    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo
    {
        return null;
    }

    public function listProducts(string $countryIso): array
    {
        if (strtoupper($countryIso) !== 'NG') {
            return [];
        }
        $out = [];
        $disc = (float) (config('services.vtpass.airtime_discount_pct') ?? 3);
        foreach (self::AIRTIME as $op => $sid) {
            $out[] = new ProductDTO($sid, 'NG', $op, 'airtime', 'Airtime', 'NGN', isFixed: false, minAmount: 50, maxAmount: 50000,
                operatorCode: $sid, costRatio: round(1 - $disc / 100, 6), costCurrency: 'NGN', meta: ['service_id' => $sid]);
        }
        foreach (self::DATA as $op => $sid) {
            try {
                $r = $this->send($this->get(), 'GET', '/service-variations', ['serviceID' => $sid])->throw();
            } catch (Throwable) {
                continue;
            }
            foreach ((array) $r->json('content.variations', $r->json('content.varations', [])) as $v) {
                $amt = (float) ($v['variation_amount'] ?? 0);
                if ($amt <= 0 || empty($v['variation_code'])) {
                    continue;
                }
                $name = (string) ($v['name'] ?? $v['variation_code']);
                $out[] = new ProductDTO($sid.'|'.$v['variation_code'], 'NG', $op, 'data', $name, 'NGN', fixedAmount: $amt, operatorCode: $sid,
                    validityText: $this->validity($name), dataMb: $this->mb($name), costNative: round($amt * (1 - $disc / 100), 4), costCurrency: 'NGN',
                    meta: ['service_id' => $sid, 'variation_code' => $v['variation_code']]);
            }
        }

        return $out;
    }

    private function mb(string $name): ?int
    {
        if (preg_match('/([\d.]+)\s*(GB|MB|TB)/i', $name, $m)) {
            return (int) round((float) $m[1] * match (strtoupper($m[2])) { 'TB' => 1048576, 'GB' => 1024, default => 1 });
        }

        return null;
    }

    private function validity(string $name): ?string
    {
        return preg_match('/(\d+)\s*(day|week|month|hour)s?/i', $name, $m) ? $m[1].' '.strtolower($m[2]).($m[1] > 1 ? 's' : '') : null;
    }

    public function purchase(PurchaseRequest $req): PurchaseResult
    {
        $phone = $this->local($req->phoneE164);
        if ($req->productType === 'data') {
            [$sid, $var] = array_pad(explode('|', $req->providerSku, 2), 2, '');
            $body = ['request_id' => $req->reference, 'serviceID' => $sid, 'billersCode' => $phone, 'variation_code' => $var, 'phone' => $phone];
        } else {
            $body = ['request_id' => $req->reference, 'serviceID' => $req->providerSku, 'amount' => (int) round($req->amount), 'phone' => $phone];
        }
        try {
            $r = $this->send($this->post(), 'POST', '/pay', $body, $req->reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Network error: requery first.');
        }
        if ($r->serverError()) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Supplier error '.$r->status().' (will requery).');
        }

        return $this->map((array) $r->json(), $req->reference);
    }

    public function requery(string $reference, ?string $providerTxnId = null): PurchaseResult
    {
        try {
            $r = $this->send($this->post(), 'POST', '/requery', ['request_id' => $reference], $reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Network error.');
        }

        return $r->successful() ? $this->map((array) $r->json(), $reference) : new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Supplier status '.$r->status());
    }

    /**
     * Map a VTpass body to one of four statuses. The INNER transaction status wins over the top-level code (spec §6.1).
     * Unknown -> pending. Code 040 / "reversed" -> reversed.
     *
     * @param  array<string, mixed>  $j
     */
    public function map(array $j, string $ref): PurchaseResult
    {
        $code = (string) ($j['code'] ?? '');
        $tx = (array) ($j['content']['transactions'] ?? []);
        $inner = strtolower((string) ($tx['status'] ?? ''));
        $txn = isset($tx['transactionId']) ? (string) $tx['transactionId'] : null;
        $msg = (string) ($j['response_description'] ?? '');

        $status = PurchaseResult::PENDING;
        $definitive = false;
        if ($code === '040' || $inner === 'reversed') {
            $status = PurchaseResult::REVERSED;
        } elseif ($inner === 'delivered' || ($inner === '' && $code === '000' && strtolower($msg) === 'transaction successful')) {
            $status = PurchaseResult::DELIVERED;
        } elseif ($inner === 'failed' || ($inner === '' && in_array($code, ['016', '013', '014', '018', '021', '022', '027', '028'], true))) {
            $status = PurchaseResult::FAILED;
            $definitive = true;
        }

        return new PurchaseResult($status, $ref, $txn, $code ?: null, $msg, isset($tx['total_amount']) ? (float) $tx['total_amount'] : null, 'NGN', $definitive, raw: $j);
    }

    private function local(string $e164): string
    {
        return str_starts_with($e164, '+234') ? '0'.substr($e164, 4) : ltrim($e164, '+');
    }

    public function balance(): array
    {
        $r = $this->send($this->get(), 'GET', '/balance')->throw();

        return ['amount' => (float) $r->json('contents.balance', 0), 'currency' => 'NGN'];
    }

    /** VTpass posts {"type":"transaction-update","data":{...}}. Authenticity: shared secret header/query (hash_equals); status is re-read by requery before money moves. */
    public function parseWebhook(Request $request): ?PurchaseResult
    {
        $secret = (string) config('services.vtpass.webhook_secret');
        $given = (string) ($request->header('X-Webhook-Secret') ?: $request->query('secret', ''));
        if ($secret === '' || ! hash_equals($secret, $given)) {
            return null;
        }
        $j = (array) $request->json()->all();
        if (($j['type'] ?? '') !== 'transaction-update' || empty($j['data']['requestId'] ?? $j['data']['request_id'] ?? null)) {
            return null;
        }
        $d = (array) $j['data'];
        $ref = (string) ($d['requestId'] ?? $d['request_id']);

        return $this->map(['code' => (string) ($d['code'] ?? ''), 'content' => ['transactions' => ['status' => $d['status'] ?? '', 'transactionId' => $d['transactionId'] ?? null]]], $ref);
    }
}
