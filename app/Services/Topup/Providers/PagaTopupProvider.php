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
 * Paga Business API as an airtime and data supplier for Nigeria (MTN, Airtel, Glo, 9mobile). Doc-confirmed (developer-docs.paga.com, Business REST API):
 * POST /paga-webservices/business-rest/secured/{getMobileOperators|getDataBundleByOperator|airtimePurchase|transactionStatus|accountBalance}
 * with headers principal = public key, credentials = secret key, hash = SHA-512 hex of the listed fields in order + the hash key. Live host www.mypaga.com, test
 * host beta.mypaga.com (follows the Paga gateway's sandbox/live mode). The calling server IP must be whitelisted on the Paga dashboard.
 * Reference numbers are idempotent on Paga's side (a repeat answers 105 Duplicate reference), so an ambiguous network failure is returned as PENDING and resolved by
 * transactionStatus (the documented status call, valid for 48 hours after the purchase), never re-sent. Same account and keys as the Paga payment gateway. UNVERIFIED until a whitelisted server calls it with a funded account.
 */
class PagaTopupProvider implements TopupProviderInterface
{
    use LogsProviderCalls;

    private const BASE_PATH = '/paga-webservices/business-rest/secured/';

    /** Codes that mean "nothing was sent and nothing will be": safe to refund or fail over. */
    private const DEFINITIVE_FAIL = [-4, 101, 103, 104, 106, 108, 109, 110, 112, 116, 117, 123, 131, 136, 137, 138, 139, 140];

    public function code(): string
    {
        return 'paga';
    }

    public function name(): string
    {
        return 'Paga';
    }

    public function configured(): bool
    {
        return filled(config('services.paga.public_key')) && filled(config('services.paga.secret_key')) && filled(config('services.paga.hash_key'));
    }

    public function newReference(): string
    {
        return 'nt'.Str::lower((string) Str::ulid());   // 28 chars, under Paga's 50 limit
    }

    /** Business host: beta.mypaga.com while the Paga gateway is in sandbox mode, www.mypaga.com otherwise (env override PAGA_BUSINESS_URL). */
    private function host(): string
    {
        $override = (string) config('services.paga.business_url', '');
        if ($override !== '') {
            return rtrim($override, '/');
        }

        return str_contains((string) config('services.paga.base_url', ''), 'beta') ? 'https://beta.mypaga.com' : 'https://www.mypaga.com';
    }

    /** @param list<string> $hashFields values hashed in the documented order, before the hash key */
    private function call(string $method, array $body, array $hashFields, int $timeout = 30, ?string $ref = null)
    {
        $hash = hash('sha512', implode('', $hashFields).(string) config('services.paga.hash_key'));
        $http = Http::acceptJson()->asJson()->timeout($timeout)->connectTimeout(5)->withHeaders([
            'principal' => (string) config('services.paga.public_key'), 'credentials' => (string) config('services.paga.secret_key'), 'hash' => $hash,
        ])->baseUrl($this->host());

        return $this->send($http, 'POST', self::BASE_PATH.$method, $body, $ref);
    }

    public function countries(): array
    {
        return ['NG'];
    }

    /** Numbers are portable in Nigeria; the customer picks the network. */
    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo
    {
        return null;
    }

    /** @return array<string, string> operator name => mobileOperatorCode (cached 12h) */
    private function operators(): array
    {
        return Cache::remember('paga.operators', 43200, function () {
            $ref = $this->newReference();
            $r = $this->call('getMobileOperators', ['referenceNumber' => $ref], [$ref])->throw();
            $out = [];
            foreach ((array) $r->json('mobileOperator', []) as $o) {
                if (! empty($o['name']) && ! empty($o['mobileOperatorCode'])) {
                    $out[$this->canonical((string) $o['name'])] = (string) $o['mobileOperatorCode'];
                }
            }

            return $out;
        });
    }

    private function canonical(string $name): string
    {
        return match (strtolower(trim($name))) {
            'mtn' => 'MTN', 'airtel' => 'Airtel', 'glo' => 'Glo', '9mobile', 'etisalat' => '9mobile', default => trim($name),
        };
    }

    public function listProducts(string $countryIso): array
    {
        if (strtoupper($countryIso) !== 'NG') {
            return [];
        }
        $disc = (float) (config('services.paga.airtime_discount_pct') ?? 0);   // our commission on airtime/data, set from the Paga agreement; 0 = assume none (margin-safe)
        $out = [];
        foreach ($this->operators() as $op => $uuid) {
            $out[] = new ProductDTO('air|'.$uuid, 'NG', $op, 'airtime', 'Airtime', 'NGN', isFixed: false, minAmount: 50, maxAmount: 50000, operatorCode: $uuid,
                costRatio: round(1 - $disc / 100, 6), costCurrency: 'NGN', meta: ['operator_uuid' => $uuid]);
            try {
                $ref = $this->newReference();
                $r = $this->call('getDataBundleByOperator', ['referenceNumber' => $ref, 'operatorPublicId' => $uuid], [$ref, $uuid])->throw();
            } catch (Throwable) {
                continue;
            }
            foreach ((array) $r->json('mobileOperatorServices', []) as $s) {
                $price = (float) ($s['servicePrice'] ?? 0);
                $id = (string) ($s['serviceId'] ?? '');
                $name = trim(preg_replace('/\s+/', ' ', (string) ($s['serviceName'] ?? '')));
                if ($price <= 0 || $id === '' || $name === '') {
                    continue;
                }
                $out[] = new ProductDTO('data|'.$uuid.'|'.$id, 'NG', $op, 'data', $name, 'NGN', fixedAmount: $price, operatorCode: $uuid, validityText: $this->validity($name),
                    dataMb: $this->mb($name), costNative: round($price * (1 - $disc / 100), 4), costCurrency: 'NGN', meta: ['operator_uuid' => $uuid, 'service_id' => $id]);
            }
        }

        return $out;
    }

    private function mb(string $name): ?int
    {
        if (preg_match('/([\d.]+)\s*(TB|GB|MB)/i', $name, $m)) {
            return (int) round((float) $m[1] * match (strtoupper($m[2])) { 'TB' => 1048576, 'GB' => 1024, default => 1 });
        }

        return null;
    }

    private function validity(string $name): ?string
    {
        if (preg_match('/(\d+)\s*(day|week|month|hr|hour)s?/i', $name, $m)) {
            $unit = strtolower($m[2]);
            $unit = in_array($unit, ['hr', 'hour'], true) ? 'hour' : $unit;

            return $m[1].' '.$unit.((int) $m[1] > 1 ? 's' : '');
        }

        return null;
    }

    public function purchase(PurchaseRequest $req): PurchaseResult
    {
        $parts = explode('|', $req->providerSku);
        $isData = ($parts[0] ?? '') === 'data' || $req->productType === 'data';
        $uuid = $isData ? ($parts[1] ?? '') : ($parts[1] ?? (string) $req->operatorCode);
        $serviceId = $isData ? ($parts[2] ?? '') : null;
        if ($uuid === '' || ($isData && ($serviceId === null || $serviceId === ''))) {
            return new PurchaseResult(PurchaseResult::FAILED, $req->reference, message: 'Unknown Paga product.', definitive: true);
        }
        $amount = $this->num($req->amount);
        $phone = $this->local($req->phoneE164);
        $body = ['referenceNumber' => $req->reference, 'amount' => $amount, 'currency' => 'NGN', 'destinationPhoneNumber' => $phone, 'mobileOperatorPublicId' => $uuid,
            'isSuppressRecipientMessages' => false, 'isDataBundle' => $isData];
        if ($isData) {
            $body['mobileOperatorServiceId'] = $serviceId;
        }
        try {
            $r = $this->call('airtimePurchase', $body, [$req->reference, $amount, $phone], 60, $req->reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Network error: check the status first, never resend.');
        }
        if ($r->serverError() || ! is_array($r->json())) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Supplier error '.$r->status().' (will check status).');
        }
        $j = (array) $r->json();
        $code = (int) ($j['responseCode'] ?? -1);
        if ($code === 0) {
            // Accepted and debited. Confirm with the status endpoint when we can; otherwise stay pending rather than assume delivery.
            $s = $this->requery($req->reference, (string) ($j['transactionId'] ?? ''));

            return $s->status === PurchaseResult::PENDING ? $this->map($j, $req->reference, PurchaseResult::PENDING) : $s;
        }

        return $this->map($j, $req->reference);
    }

    public function requery(string $reference, ?string $providerTxnId = null): PurchaseResult
    {
        try {
            $r = $this->call('transactionStatus', ['referenceNumber' => $reference], [$reference], 30, $reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Network error.');
        }
        if (! $r->successful() || ! is_array($r->json())) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Supplier status '.$r->status());
        }

        return $this->map((array) $r->json(), $reference);
    }

    /**
     * Map a Paga body to one of four statuses. transactionStatus (when present) wins over responseCode. Unknown or code 3 means pending; only a listed
     * "nothing was sent" code is a definitive failure. 105 (duplicate reference) is never a failure: the first request exists, so it stays pending for a status check.
     *
     * @param  array<string, mixed>  $j
     */
    public function map(array $j, string $ref, ?string $force = null): PurchaseResult
    {
        $code = (int) ($j['responseCode'] ?? -1);
        $ts = strtoupper((string) ($j['transactionStatus'] ?? $j['status'] ?? ''));
        $txn = isset($j['transactionId']) ? (string) $j['transactionId'] : null;
        $msg = (string) ($j['message'] ?? '');
        $status = PurchaseResult::PENDING;
        $definitive = false;
        if ($force !== null) {
            $status = $force;
        } elseif (in_array($ts, ['SUCCESSFUL', 'SUCCESS', 'COMPLETED'], true) || ($ts === '' && $code === 0)) {
            $status = PurchaseResult::DELIVERED;
        } elseif (in_array($ts, ['FAILED', 'DECLINED', 'CANCELLED'], true)) {
            $status = PurchaseResult::FAILED;
            $definitive = true;
        } elseif (in_array($ts, ['REVERSED', 'REFUNDED'], true)) {
            $status = PurchaseResult::REVERSED;
        } elseif ($ts === '' && in_array($code, self::DEFINITIVE_FAIL, true)) {
            $status = PurchaseResult::FAILED;
            $definitive = true;
        }

        return new PurchaseResult($status, $ref, $txn, (string) $code, $msg, isset($j['fee']) ? (float) $j['fee'] : null, 'NGN', $definitive, raw: $j);
    }

    private function num(float $v): string
    {
        return floor($v) == $v ? (string) (int) $v : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    private function local(string $e164): string
    {
        return str_starts_with($e164, '+234') ? '0'.substr($e164, 4) : ltrim($e164, '+');
    }

    public function balance(): array
    {
        $ref = $this->newReference();
        $r = $this->call('accountBalance', ['referenceNumber' => $ref], [$ref])->throw();

        return ['amount' => (float) ($r->json('totalBalance') ?? $r->json('availableBalance') ?? 0), 'currency' => 'NGN'];
    }

    /** Paga posts no airtime webhook; orders are resolved by polling transactionStatus. */
    public function parseWebhook(Request $request): ?PurchaseResult
    {
        return null;
    }
}
