<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\Pricing\CurrencyService;
use Illuminate\Support\Facades\Http;

/**
 * What each payment gateway ACCOUNT can really take, asked of the gateway's own API instead of a table written from documentation.
 *
 * A gateway's public docs list every currency the company supports across all countries, but an account only transacts the currencies
 * enabled for it (a Nigerian Paystack business answers "Currency not supported by merchant" for GHS, KES and ZAR). The wallet used to
 * offer whatever the table said, then hide the provider's answer behind a generic message. Now:
 *
 *   - Paystack:    GET /balance               -> the currencies on the account
 *   - Flutterwave: GET /balances              -> the currencies enabled on the account
 *   - Stripe:      GET /account + /country_specs/{country} -> the currencies the account's country can charge
 *   - the rest (PayPal, crypto rails, Payssion): no capability endpoint exists, so the documented list stays and is marked as such.
 *
 * Detection is a network call, so it never runs while a customer waits: it runs from the scheduler, from the admin "Check" button, and
 * from the queue when a key is saved. The wallet only READS the stored result (a Setting, so it survives cache clears). A currency the
 * provider refuses at checkout is learned and dropped from the stored list, so the next customer is never offered it.
 */
class GatewayCapabilities
{
    private const KEY = 'payments.capabilities.';

    /** Gateways whose own API can tell us (the rest keep the documented list). */
    public const DETECTABLE = ['paystack', 'flutterwave', 'stripe'];

    /** @return array{currencies: list<string>, source: string, checked_at: ?string, error: ?string}|null */
    public static function snapshot(string $gateway): ?array
    {
        try {
            $raw = Setting::getValue(self::KEY.$gateway);
        } catch (\Throwable) {
            return null;   // no app or no settings table yet (a fresh install, a pure unit test): the documented list applies
        }
        $data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

        return is_array($data) && isset($data['currencies']) ? $data + ['source' => 'api', 'checked_at' => null, 'error' => null] : null;
    }

    /** Currencies the account answered with (limited to what the platform models), or null when nothing has been detected yet. */
    public static function detected(string $gateway): ?array
    {
        if (! in_array($gateway, self::DETECTABLE, true)) {
            return null;
        }
        $snap = self::snapshot($gateway);

        return $snap === null ? null : array_values(array_filter(array_map('strtoupper', $snap['currencies']), fn ($c) => isset(CurrencyService::SUPPORTED[$c])));
    }

    /** Ask the gateway now. Stores and returns the snapshot; on a failed call the previous good snapshot is kept and the error is recorded. */
    public static function refresh(string $gateway): array
    {
        $previous = self::snapshot($gateway);
        try {
            $currencies = match ($gateway) {
                'paystack' => self::paystack(),
                'flutterwave' => self::flutterwave(),
                'stripe' => self::stripe(),
                default => throw new \RuntimeException('This gateway has no capability endpoint.'),
            };
            $snap = ['currencies' => $currencies, 'source' => 'api', 'checked_at' => now()->toIso8601String(), 'error' => null];
        } catch (\Throwable $e) {
            $snap = ($previous ?? ['currencies' => [], 'source' => 'api']) + ['checked_at' => $previous['checked_at'] ?? null];
            $snap['error'] = mb_substr($e->getMessage(), 0, 240);
            $snap['failed_at'] = now()->toIso8601String();
            if ($previous === null) {
                // never detected and cannot detect: store nothing usable so the documented list keeps applying
                Setting::setValue(self::KEY.$gateway.'.error', $snap['error'], 'payments');

                return $snap;
            }
        }
        Setting::setValue(self::KEY.$gateway, json_encode($snap), 'payments');

        return $snap;
    }

    /** @return array<string, array> gateway => snapshot, for every gateway that has keys. */
    public static function refreshAll(): array
    {
        $out = [];
        foreach (self::DETECTABLE as $gw) {
            if (ProviderStatus::isActive($gw)) {
                $out[$gw] = self::refresh($gw);
            }
        }

        return $out;
    }

    /** The provider just refused this currency at checkout: stop offering it (kept until the next successful detection). */
    public static function learnUnsupported(string $gateway, string $currency): void
    {
        $snap = self::snapshot($gateway);
        $currency = strtoupper($currency);
        $list = $snap ? array_map('strtoupper', $snap['currencies']) : array_map('strtoupper', GatewayCurrencyMatrix::MATRIX[$gateway]['currencies'] ?? []);
        $snap = ($snap ?? ['source' => 'learned', 'checked_at' => null, 'error' => null]);
        $snap['currencies'] = array_values(array_diff($list, [$currency]));
        $snap['learned'] = array_values(array_unique(array_merge($snap['learned'] ?? [], [$currency])));
        Setting::setValue(self::KEY.$gateway, json_encode($snap), 'payments');
    }

    /** True when a provider's error text means "this account cannot take that currency". */
    public static function isCurrencyRefusal(?string $message): bool
    {
        return $message !== null && (bool) preg_match('/currency not supported|not supported by merchant|unsupported currency|currency .*(not|isn.t) (supported|enabled|available)|no active channel/i', $message);
    }

    /** @return list<string> */
    private static function paystack(): array
    {
        $res = Http::withToken((string) config('services.paystack.secret_key'))->acceptJson()->timeout(15)->connectTimeout(4)
            ->get(rtrim((string) config('services.paystack.base_url'), '/').'/balance');
        if (! $res->successful() || data_get($res->json(), 'status') !== true) {
            throw new \RuntimeException('Paystack: '.((string) (data_get($res->json(), 'message') ?: 'HTTP '.$res->status())));
        }

        return self::codes((array) data_get($res->json(), 'data', []), 'currency');
    }

    /** @return list<string> */
    private static function flutterwave(): array
    {
        $res = Http::withToken((string) config('services.flutterwave.secret_key'))->acceptJson()->timeout(15)->connectTimeout(4)
            ->get(rtrim((string) config('services.flutterwave.base_url'), '/').'/balances');
        if (! $res->successful() || data_get($res->json(), 'status') !== 'success') {
            throw new \RuntimeException('Flutterwave: '.((string) (data_get($res->json(), 'message') ?: 'HTTP '.$res->status())));
        }

        return self::codes((array) data_get($res->json(), 'data', []), 'currency');
    }

    /** @return list<string> */
    private static function stripe(): array
    {
        $base = rtrim((string) config('services.stripe.base_url'), '/');
        $http = Http::withToken((string) config('services.stripe.secret_key'))->acceptJson()->timeout(15)->connectTimeout(4);
        $acct = $http->get($base.'/account');
        if (! $acct->successful()) {
            throw new \RuntimeException('Stripe: '.((string) (data_get($acct->json(), 'error.message') ?: 'HTTP '.$acct->status())));
        }
        $country = (string) data_get($acct->json(), 'country');
        $spec = $http->get($base.'/country_specs/'.$country);
        if (! $spec->successful()) {
            throw new \RuntimeException('Stripe: '.((string) (data_get($spec->json(), 'error.message') ?: 'HTTP '.$spec->status())));
        }

        return array_values(array_unique(array_map('strtoupper', (array) data_get($spec->json(), 'supported_payment_currencies', []))));
    }

    /** @return list<string> */
    private static function codes(array $rows, string $field): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($r) => strtoupper((string) data_get($r, $field)), $rows))));
    }
}
