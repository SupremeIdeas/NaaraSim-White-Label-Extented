<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Binance Pay (blueprint Section 14.2 — added gateway, crypto rail). Uses the
 * documented merchant API v3: each request is signed HMAC-SHA512(uppercase) over
 * "<timestamp>\n<nonce>\n<body>\n" with the API secret, in the BinancePay-*
 * headers. The webhook is verified with the SAME scheme (money-safety rule 9)
 * before the payload is trusted. Amounts are settled in the order currency.
 * Degrades safely when unconfigured.
 */
class BinancePayGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'binance';
    }

    private function configured(): bool
    {
        return ! empty(config('services.binance.api_key'))
            && ! empty(config('services.binance.api_secret'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.binance.base_url', 'https://bpay.binanceapi.com'), '/');
    }

    /** HMAC-SHA512 (uppercase hex) over "<ts>\n<nonce>\n<body>\n". */
    private function sign(string $timestamp, string $nonce, string $body): string
    {
        $payload = $timestamp."\n".$nonce."\n".$body."\n";

        return strtoupper(hash_hmac('sha512', $payload, (string) config('services.binance.api_secret')));
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $body = json_encode([
            'env' => ['terminalType' => 'WEB'],
            'merchantTradeNo' => str_replace('-', '', $reference),
            'orderAmount' => (float) number_format($amount, 2, '.', ''),
            'currency' => strtoupper($currency),
            'description' => 'NaaraSim wallet top-up',
            'goodsDetails' => [[
                'goodsType' => '02', 'goodsCategory' => '6000',
                'referenceGoodsId' => (string) $user->id, 'goodsName' => 'Wallet top-up',
            ]],
            'passThroughInfo' => json_encode(['user_id' => $user->id, 'reference' => $reference]),
            'returnUrl' => $meta['return_url'] ?? config('app.url').'/wallet',
            'cancelUrl' => $meta['cancel_url'] ?? config('app.url').'/wallet',
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = (string) round(microtime(true) * 1000);
        $nonce = Str::random(32);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'BinancePay-Timestamp' => $timestamp,
            'BinancePay-Nonce' => $nonce,
            'BinancePay-Certificate-SN' => (string) config('services.binance.api_key'),
            'BinancePay-Signature' => $this->sign($timestamp, $nonce, $body),
        ])->withBody($body, 'application/json')
            ->post($this->base().'/binancepay/openapi/v3/order')
            ->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) data_get($response, 'data.universalUrl', data_get($response, 'data.checkoutUrl', '')),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $timestamp = (string) $request->header('BinancePay-Timestamp', '');
        $nonce = (string) $request->header('BinancePay-Nonce', '');
        $signature = (string) $request->header('BinancePay-Signature', '');
        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return false;
        }

        $expected = $this->sign($timestamp, $nonce, $request->getContent());

        return hash_equals($expected, strtoupper($signature));
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        // The webhook body wraps the order notification; bizStatus carries the
        // outcome and `data` is a JSON string of the order.
        $bizStatus = (string) $request->input('bizStatus', '');
        $data = json_decode((string) $request->input('data', '{}'), true) ?: [];
        $pass = json_decode((string) data_get($data, 'passThroughInfo', '{}'), true) ?: [];

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) (data_get($pass, 'reference') ?? data_get($data, 'merchantTradeNo', '')),
            userId: ($uid = data_get($pass, 'user_id')) !== null ? (int) $uid : null,
            amount: (float) data_get($data, 'orderAmount', 0),
            // USD-pegged stablecoins credit the USD wallet 1:1 (the wallet holds
            // NGN/USD, not crypto). Non-stable rails would need FX before credit.
            currency: $this->walletCurrency((string) data_get($data, 'currency', 'USDT')),
            status: $bizStatus === 'PAY_SUCCESS' ? 'success' : 'failed',
            raw: $request->all(),
        );
    }

    /** Map a Binance settlement currency onto a supported wallet currency. */
    private function walletCurrency(string $currency): string
    {
        $currency = strtoupper($currency);

        return in_array($currency, ['USDT', 'USDC', 'BUSD', 'USD'], true) ? 'USD' : $currency;
    }
}
