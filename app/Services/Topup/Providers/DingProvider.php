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
 * Ding (DingConnect). Endpoints, SendTransfer rules (SkuCode only from GetProducts, one of SendValue/ReceiveValue, duplicate DistributorRef
 * rejected, ValidateOnly, 90s timeout = not charged) are doc-confirmed in the spec. UNVERIFIED until a key is saved: auth header name
 * (legacy `api_key`), the ProcessingState / ResultCode enums. Ding has no webhook: it is polled with ListTransferRecords.
 */
class DingProvider implements TopupProviderInterface
{
    use LogsProviderCalls;

    public function code(): string
    {
        return 'ding';
    }

    public function name(): string
    {
        return 'Ding';
    }

    public function configured(): bool
    {
        return filled(config('services.ding.api_key'));
    }

    public function newReference(): string
    {
        return 'nt-'.Str::lower((string) Str::ulid());
    }

    private function client(int $timeout = 100): PendingRequest
    {
        return Http::acceptJson()->baseUrl(rtrim((string) config('services.ding.base_url'), '/'))
            ->withHeaders(['api_key' => (string) config('services.ding.api_key')])->timeout($timeout);
    }

    /**
     * Read-only GET with a retry: Ding's edge answers an intermittent 401 AuthenticationFailed to a valid key (seen live, roughly every other
     * call). Reads are safe to repeat; SendTransfer is NEVER sent through here.
     *
     * @param  array<string, mixed>  $query
     */
    private function read(string $path, array $query = [], int $timeout = 60): \Illuminate\Http\Client\Response
    {
        $r = null;
        for ($i = 0; $i < 7; $i++) {
            $r = $this->send($this->client($timeout), 'GET', $path, $query);
            if ($r->status() !== 401 && (int) $r->json('ResultCode', 1) !== 4) {
                return $r;
            }
            usleep(400000 * ($i + 1));
        }

        return $r;
    }

    public function countries(): array
    {
        return Cache::remember('topup.ding.countries', 21600, function () {
            try {
                $list = collect((array) $this->read('/GetCountries', [], 30)->throw()->json('Items', []))->pluck('CountryIso')->filter()->map('strtoupper')->values()->all();
                if (! $list) {
                    Cache::forget('topup.ding.countries');
                }

                return $list;
            } catch (Throwable) {
                Cache::forget('topup.ding.countries');

                return [];
            }
        });
    }

    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo
    {
        try {
            $r = $this->read('/GetAccountLookup', ['accountNumber' => $e164Phone], 30);
        } catch (Throwable) {
            return null;
        }
        $item = (array) ($r->json('Items.0') ?? []);
        if (! $item || empty($item['ProviderCode'])) {
            return null;
        }
        $names = $this->providerNames($countryIso);

        return new RecipientInfo(strtoupper((string) ($item['RegionCode'] ?? $countryIso ?? '')), $names[$item['ProviderCode']] ?? (string) $item['ProviderCode'], (string) $item['ProviderCode']);
    }

    /** @return array<string, string> ProviderCode => display name, per country; a failed or empty answer is never cached */
    private function providerNames(?string $iso = null): array
    {
        $iso = strtoupper((string) $iso);
        $key = 'topup.ding.providers.'.($iso ?: 'all');
        if (is_array($hit = Cache::get($key)) && $hit) {
            return $hit;
        }
        try {
            $r = $this->read('/GetProviders', $iso !== '' ? ['countryIsos' => $iso] : [], 90)->throw();
            $cn = $iso !== '' ? $this->countryName($iso) : '';
            $map = collect((array) $r->json('Items', []))->mapWithKeys(fn ($p) => [(string) $p['ProviderCode'] => $this->brand((string) ($p['Name'] ?? $p['ProviderCode']), $cn)])->all();
        } catch (Throwable) {
            return [];
        }
        if ($map) {
            Cache::put($key, $map, 21600);
        }

        return $map;
    }

    public function listProducts(string $countryIso): array
    {
        $r = $this->read('/GetProducts', ['countryIsos' => strtoupper($countryIso)])->throw();
        $names = $this->providerNames($countryIso);
        $out = [];
        foreach ((array) $r->json('Items', []) as $p) {
            $sku = (string) ($p['SkuCode'] ?? '');
            $min = (array) ($p['Minimum'] ?? []);
            $max = (array) ($p['Maximum'] ?? []);
            if ($sku === '' || ! isset($min['SendValue'], $max['SendValue'])) {
                continue;
            }
            $benefits = array_map('strtolower', (array) ($p['Benefits'] ?? []));
            $isData = (bool) array_filter($benefits, fn ($b) => str_contains($b, 'data'));
            $isAir = (bool) array_filter($benefits, fn ($b) => str_contains($b, 'mobile') || str_contains($b, 'airtime') || str_contains($b, 'minutes'));
            if (! $isData && ! $isAir && $benefits) {
                continue;                                        // utilities, gift cards, PINs we do not sell here
            }
            if (! empty($p['LookupBillsRequired'])) {
                continue;
            }
            $fixed = abs((float) $min['SendValue'] - (float) $max['SendValue']) < 0.0001;
            $recvCur = strtoupper((string) ($min['ReceiveCurrencyIso'] ?? $min['SendCurrencyIso'] ?? 'USD'));
            $sendCur = strtoupper((string) ($min['SendCurrencyIso'] ?? 'USD'));
            $ratio = (float) $min['ReceiveValue'] > 0 ? (float) $min['SendValue'] / (float) $min['ReceiveValue'] : null;
            $out[] = new ProductDTO(
                $sku, strtoupper($countryIso), $names[$p['ProviderCode'] ?? ''] ?? (string) ($p['ProviderCode'] ?? 'Operator'), ($isData && ! $isAir) || ($isData && $fixed) ? 'data' : 'airtime',
                (string) ($p['DefaultDisplayText'] ?? 'Top-up'), $recvCur, isFixed: $fixed, fixedAmount: $fixed ? (float) $min['ReceiveValue'] : null,
                minAmount: $fixed ? null : (float) $min['ReceiveValue'], maxAmount: $fixed ? null : (float) $max['ReceiveValue'], operatorCode: (string) ($p['ProviderCode'] ?? ''),
                validityText: $this->validity((string) ($p['ValidityPeriodIso'] ?? '')), costNative: $fixed ? (float) $min['SendValue'] : null,
                costRatio: $fixed ? null : $ratio, costCurrency: $sendCur,
                meta: ['uat_number' => $p['UatNumber'] ?? null, 'send_currency' => $sendCur],
            );
        }

        return $out;
    }

    /** "MTN Nigeria Data" and "MTN Nigeria" are one brand to a customer: drop the Data suffix and the country name so the offer groups under "MTN". */
    private function brand(string $name, string $country): string
    {
        $n = trim((string) preg_replace('/\s+Data$/i', '', trim($name)));
        if ($country !== '' && preg_match('/^(.+?)\s+'.preg_quote($country, '/').'$/iu', $n, $m)) {
            $n = trim($m[1]);
        }

        return $n !== '' ? $n : $name;
    }

    private function countryName(string $iso): string
    {
        $names = Cache::remember('topup.ding.countrynames', 86400, function () {
            try {
                return collect((array) $this->read('/GetCountries', [], 30)->json('Items', []))->mapWithKeys(fn ($c) => [strtoupper((string) ($c['CountryIso'] ?? '')) => (string) ($c['CountryName'] ?? '')])->all();
            } catch (Throwable) {
                return [];
            }
        });

        return (string) ($names[strtoupper($iso)] ?? '');
    }

    private function validity(string $iso): ?string
    {
        if (preg_match('/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?/', $iso, $m) && $iso !== '') {
            $d = (int) ($m[1] ?? 0) * 365 + (int) ($m[2] ?? 0) * 30 + (int) ($m[3] ?? 0) * 7 + (int) ($m[4] ?? 0);

            return $d > 0 ? $d.' day'.($d > 1 ? 's' : '') : null;
        }

        return null;
    }

    public function purchase(PurchaseRequest $req): PurchaseResult
    {
        // Ding takes SendValue in OUR send currency; we ask by ReceiveValue so the recipient gets exactly what was quoted.
        $body = ['SkuCode' => $req->providerSku, 'ReceiveValue' => $req->amount, 'SendValue' => 0, 'AccountNumber' => $req->phoneE164, 'DistributorRef' => $req->reference, 'ValidateOnly' => false];
        if ($req->isFixed) {
            $body['SendValue'] = (float) ($req->meta['cost_native'] ?? 0);
            $body['ReceiveValue'] = 0;
        }
        try {
            $r = $this->send($this->client(100), 'POST', '/SendTransfer', $body, $req->reference);
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
            $r = $this->send($this->client(30), 'POST', '/ListTransferRecords', ['DistributorRef' => $reference, 'Take' => 1], $reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Network error.');
        }
        $item = (array) ($r->json('Items.0') ?? []);
        if (! $r->successful() || ! $item) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Supplier has no record yet.');
        }

        return $this->map(['TransferRecord' => $item, 'ResultCode' => 1, 'ErrorCodes' => []], $reference);
    }

    /**
     * ProcessingState: Complete -> delivered; Submitted/Pending/Processing/ProviderTimedOut -> pending; Failed/Cancelled/Refunded -> failed.
     * An ErrorCodes entry with no TransferRecord means nothing was submitted (definitive). Unknown -> pending.
     *
     * @param  array<string, mixed>  $j
     */
    public function map(array $j, string $ref): PurchaseResult
    {
        $rec = (array) ($j['TransferRecord'] ?? []);
        $state = strtolower((string) ($rec['ProcessingState'] ?? ''));
        $errors = (array) ($j['ErrorCodes'] ?? []);
        $code = (string) ($errors[0]['Code'] ?? '');
        $msg = (string) ($errors[0]['Context'] ?? $errors[0]['Code'] ?? '');

        if ($code === 'ProviderTimedOut' || $code === 'ProviderError' || $code === 'RateLimited') {
            $status = PurchaseResult::PENDING;
        } elseif ($state === 'complete') {
            $status = PurchaseResult::DELIVERED;
        } elseif (in_array($state, ['failed', 'cancelled', 'refunded'], true)) {
            $status = PurchaseResult::FAILED;
        } elseif ($errors && ! $rec) {
            $status = PurchaseResult::FAILED;
        } else {
            $status = PurchaseResult::PENDING;
        }

        return new PurchaseResult($status, $ref, isset($rec['TransferId']['TransferRef']) ? (string) $rec['TransferId']['TransferRef'] : (isset($rec['TransferId']) && is_scalar($rec['TransferId']) ? (string) $rec['TransferId'] : null),
            $code ?: ($rec['ProcessingState'] ?? null), $msg, isset($rec['Price']['SendValue']) ? (float) $rec['Price']['SendValue'] : null, $rec['Price']['SendCurrencyIso'] ?? null,
            definitive: $status === PurchaseResult::FAILED, receiptText: $rec['ReceiptText'] ?? null, raw: $j);
    }

    public function balance(): array
    {
        $r = $this->read('/GetBalance', [], 30)->throw();

        return ['amount' => (float) $r->json('Balance', 0), 'currency' => (string) $r->json('CurrencyIso', 'USD')];
    }

    /** Ding documents no webhook: this adapter is polled via requery(). */
    public function parseWebhook(Request $request): ?PurchaseResult
    {
        return null;
    }
}
