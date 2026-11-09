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
 * Reloadly Airtime (international). OAuth2 client credentials, token cached with a lock and refreshed early. Built to the spec;
 * the hosts, /operators/countries/{ISO}, /topups and token lifetimes are doc-confirmed. UNVERIFIED until sandbox keys are saved:
 * the Accept header value, auto-detect + status paths, bundle representation, duplicate customIdentifier behaviour, webhook auth.
 */
class ReloadlyTopupProvider implements TopupProviderInterface
{
    use LogsProviderCalls;

    public function code(): string
    {
        return 'reloadly';
    }

    public function name(): string
    {
        return 'Reloadly';
    }

    public function configured(): bool
    {
        return filled(config('services.reloadly.client_id')) && filled(config('services.reloadly.client_secret'));
    }

    public function newReference(): string
    {
        return 'nt-'.Str::lower((string) Str::ulid());
    }

    private function token(): string
    {
        return Cache::lock('topup.reloadly.token.lock', 15)->block(10, function () {
            return Cache::remember('topup.reloadly.token', 60 * 60 * 24 * 40, function () {   // 60-day live tokens; refresh at ~2/3; sandbox tokens last 24h so the cache is short-circuited below
                $r = Http::acceptJson()->timeout(20)->post((string) config('services.reloadly.auth_url'), [
                    'client_id' => config('services.reloadly.client_id'), 'client_secret' => config('services.reloadly.client_secret'),
                    'grant_type' => 'client_credentials', 'audience' => config('services.reloadly_topup.audience'),
                ])->throw();
                $ttl = (int) $r->json('expires_in', 86400);
                Cache::put('topup.reloadly.token.exp', now()->addSeconds((int) ($ttl * 0.8)));

                return (string) $r->json('access_token');
            });
        });
    }

    private function client(): PendingRequest
    {
        $exp = Cache::get('topup.reloadly.token.exp');
        if ($exp && now()->greaterThan($exp)) {
            Cache::forget('topup.reloadly.token');
        }

        return Http::withToken($this->token())->baseUrl(rtrim((string) config('services.reloadly_topup.base_url'), '/'))
            ->withHeaders(['Accept' => (string) config('services.reloadly_topup.accept')])->timeout(45);
    }

    public function countries(): array
    {
        return Cache::remember('topup.reloadly.countries', 21600, function () {
            try {
                return collect((array) $this->send($this->client(), 'GET', '/countries')->throw()->json())->pluck('isoName')->filter()->map('strtoupper')->values()->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo
    {
        if (! $countryIso) {
            return null;
        }
        $national = preg_replace('/^\+/', '', $e164Phone);
        try {
            $r = $this->send($this->client(), 'GET', "/operators/auto-detect/phone/{$national}/countries/".strtoupper($countryIso));
        } catch (Throwable) {
            return null;
        }
        if (! $r->successful() || ! $r->json('name')) {
            return null;
        }

        return new RecipientInfo(strtoupper($countryIso), (string) $r->json('name'), (string) $r->json('operatorId'));
    }

    public function listProducts(string $countryIso): array
    {
        $r = $this->send($this->client(), 'GET', '/operators/countries/'.strtoupper($countryIso), ['includeBundles' => 'true', 'includeData' => 'true', 'size' => 200])->throw();
        $ops = (array) ($r->json('content') ?? $r->json() ?? []);
        $out = [];
        foreach ($ops as $op) {
            if (! is_array($op) || empty($op['operatorId'])) {
                continue;
            }
            $name = (string) ($op['name'] ?? 'Operator');
            $local = strtoupper((string) ($op['destinationCurrencyCode'] ?? 'USD'));
            $sendCur = strtoupper((string) ($op['senderCurrencyCode'] ?? 'USD'));
            $discount = (float) ($op['internationalDiscount'] ?? $op['localDiscount'] ?? 0);
            $fx = (float) ($op['fx']['rate'] ?? 0);             // sender currency per 1 destination unit... (⚠ confirm direction in sandbox)
            $type = ! empty($op['data']) || ! empty($op['bundle']) ? 'data' : 'airtime';
            $base = ['operatorCode' => (string) $op['operatorId'], 'meta' => ['operator_id' => $op['operatorId'], 'discount_pct' => $discount, 'sender_currency' => $sendCur]];

            $fixedMap = (array) ($op['localFixedAmounts'] ?? []);
            $fixedSend = (array) ($op['fixedAmounts'] ?? []);
            if (($op['denominationType'] ?? '') === 'FIXED' && ($fixedSend || $fixedMap)) {
                $descr = (array) ($op['fixedAmountsDescriptions'] ?? []);
                foreach ($fixedSend as $i => $amt) {
                    $dest = (float) ($fixedMap[$i] ?? 0);
                    $cost = round((float) $amt * (1 - $discount / 100), 4);
                    $label = (string) ($descr[(string) $amt] ?? $descr[$amt] ?? ($type === 'data' ? 'Data bundle' : 'Airtime'));
                    $out[] = new ProductDTO($op['operatorId'].'|'.$amt, strtoupper($countryIso), $name, $type, $label, $dest > 0 ? $local : $sendCur,
                        fixedAmount: $dest > 0 ? $dest : (float) $amt, costNative: $cost, costCurrency: $sendCur, operatorCode: $base['operatorCode'], meta: $base['meta']);
                }
            } else {
                $min = (float) ($op['minAmount'] ?? 0);
                $max = (float) ($op['maxAmount'] ?? 0);
                if ($max <= 0) {
                    continue;
                }
                $useLocal = ! empty($op['localMinAmount']) && $fx > 0;
                $out[] = new ProductDTO((string) $op['operatorId'], strtoupper($countryIso), $name, 'airtime', 'Airtime', $useLocal ? $local : $sendCur, isFixed: false,
                    minAmount: $useLocal ? (float) $op['localMinAmount'] : $min, maxAmount: $useLocal ? (float) ($op['localMaxAmount'] ?? $max) : $max,
                    costRatio: $useLocal ? round((1 - $discount / 100) / $fx, 8) : round(1 - $discount / 100, 8), costCurrency: $sendCur, operatorCode: $base['operatorCode'], meta: $base['meta'] + ['use_local' => $useLocal]);
            }
        }

        return $out;
    }

    public function purchase(PurchaseRequest $req): PurchaseResult
    {
        [$opId] = array_pad(explode('|', $req->providerSku, 2), 2, '');
        [$cc, $num] = $this->split($req->phoneE164, $req->countryIso);
        $body = [
            'operatorId' => (int) $opId, 'amount' => $req->amount, 'useLocalAmount' => (bool) ($req->meta['use_local'] ?? false),
            'customIdentifier' => $req->reference, 'recipientPhone' => ['countryCode' => $cc, 'number' => $num],
        ];
        if ($req->senderEmail) {
            $body['recipientEmail'] = $req->senderEmail;
        }
        try {
            $r = $this->send($this->client(), 'POST', '/topups', $body, $req->reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Network error: requery first.');
        }
        if ($r->serverError()) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Supplier error '.$r->status().' (will requery).');
        }
        if ($r->failed()) {
            return new PurchaseResult(PurchaseResult::FAILED, $req->reference, providerCode: (string) $r->json('errorCode'), message: (string) ($r->json('message') ?: 'Rejected.'), definitive: true, raw: (array) $r->json());
        }

        return $this->map((array) $r->json(), $req->reference);
    }

    public function requery(string $reference, ?string $providerTxnId = null): PurchaseResult
    {
        if (! $providerTxnId) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'No supplier transaction id yet.');
        }
        try {
            $r = $this->send($this->client(), 'GET', "/topups/{$providerTxnId}/status", [], $reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Network error.');
        }

        return $r->successful() ? $this->map((array) $r->json(), $reference) : new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Supplier status '.$r->status());
    }

    /** @param  array<string, mixed>  $j */
    public function map(array $j, string $ref): PurchaseResult
    {
        $st = strtoupper((string) ($j['status'] ?? ''));
        $status = match ($st) {
            'SUCCESSFUL' => PurchaseResult::DELIVERED,
            'FAILED', 'REFUNDED' => PurchaseResult::FAILED,
            default => PurchaseResult::PENDING,           // PENDING / PROCESSING / unknown
        };

        return new PurchaseResult($status, $ref, isset($j['transactionId']) ? (string) $j['transactionId'] : null, $st ?: null, (string) ($j['message'] ?? ''),
            isset($j['balanceInfo']['cost']) ? (float) $j['balanceInfo']['cost'] : null, $j['balanceInfo']['currencyCode'] ?? null,
            definitive: $status === PurchaseResult::FAILED, raw: $j);
    }

    /** @return array{0: string, 1: string} */
    private function split(string $e164, string $iso): array
    {
        try {
            $u = \libphonenumber\PhoneNumberUtil::getInstance()->parse($e164, null);

            return [strtoupper($iso), (string) $u->getNationalNumber()];
        } catch (Throwable) {
            return [strtoupper($iso), ltrim($e164, '+')];
        }
    }

    public function balance(): array
    {
        $r = $this->send($this->client(), 'GET', '/accounts/balance')->throw();

        return ['amount' => (float) $r->json('balance', 0), 'currency' => (string) $r->json('currencyCode', 'USD')];
    }

    /** Reloadly sends a top-up callback only if enabled on the account (⚠ confirm). Auth: shared secret (hash_equals); status is re-read by requery. */
    public function parseWebhook(Request $request): ?PurchaseResult
    {
        $secret = (string) config('services.reloadly.webhook_secret');
        $given = (string) ($request->header('X-Webhook-Secret') ?: $request->query('secret', ''));
        if ($secret === '' || ! hash_equals($secret, $given)) {
            return null;
        }
        $j = (array) ($request->json('data') ?? $request->json()->all());
        if (empty($j['customIdentifier'])) {
            return null;
        }

        return $this->map($j, (string) $j['customIdentifier']);
    }
}
