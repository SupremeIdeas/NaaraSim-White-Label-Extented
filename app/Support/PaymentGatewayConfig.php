<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Per-gateway operational config for the admin Payment Gateways screen
 * (BUILD-2 §3): the sandbox/live mode toggle, the read-only webhook + callback
 * URLs to register on the provider dashboard, and a live test-connection ping.
 *
 * Keys stay in Admin → Provider Keys (one credential system — not duplicated).
 * The mode is a small per-gateway Setting; for gateways whose sandbox and live
 * API live on DIFFERENT hosts (PayPal, NOWPayments) it swaps the base URL at
 * boot via applyToConfig(). For gateways that key sandbox/live by prefix
 * (Stripe sk_test_, Paystack, Flutterwave) the mode is informational and the
 * live PaymentSandbox detector reads the actual key.
 */
class PaymentGatewayConfig
{
    private const MODE_PREFIX = 'payments.mode.';

    /**
     * @var array<string, array{label:string, sandbox_host:?string, live_host:?string, testable:bool}>
     */
    public const GATEWAYS = [
        'paystack' => ['label' => 'Paystack', 'sandbox_host' => null, 'live_host' => null, 'testable' => true],
        'flutterwave' => ['label' => 'Flutterwave', 'sandbox_host' => null, 'live_host' => null, 'testable' => true],
        'stripe' => ['label' => 'Stripe', 'sandbox_host' => null, 'live_host' => null, 'testable' => true],
        'paypal' => ['label' => 'PayPal', 'sandbox_host' => 'https://api-m.sandbox.paypal.com', 'live_host' => 'https://api-m.paypal.com', 'testable' => true],
        'nowpayments' => ['label' => 'NOWPayments', 'sandbox_host' => 'https://api-sandbox.nowpayments.io', 'live_host' => 'https://api.nowpayments.io', 'testable' => true],
        'binance' => ['label' => 'Binance Pay', 'sandbox_host' => null, 'live_host' => null, 'testable' => false],
        'cryptomus' => ['label' => 'Cryptomus', 'sandbox_host' => null, 'live_host' => null, 'testable' => false],
        'coinpayments' => ['label' => 'CoinPayments', 'sandbox_host' => null, 'live_host' => null, 'testable' => false],
        'payssion' => ['label' => 'Payssion', 'sandbox_host' => null, 'live_host' => null, 'testable' => false],
    ];

    public static function isGateway(string $gateway): bool
    {
        return array_key_exists($gateway, self::GATEWAYS);
    }

    /** The stored mode for a gateway: 'sandbox' or 'live' (default live). */
    public static function mode(string $gateway): string
    {
        $v = (string) Setting::getValue(self::MODE_PREFIX.$gateway, 'live');

        return $v === 'sandbox' ? 'sandbox' : 'live';
    }

    public static function setMode(string $gateway, string $mode): void
    {
        Setting::setValue(self::MODE_PREFIX.$gateway, $mode === 'sandbox' ? 'sandbox' : 'live', 'payments');
    }

    /** True for gateways whose sandbox/live API is on a different host. */
    public static function hasDistinctHosts(string $gateway): bool
    {
        return (self::GATEWAYS[$gateway]['sandbox_host'] ?? null) !== null;
    }

    /**
     * Overlay the mode-appropriate base URL for the gateways that have distinct
     * sandbox/live hosts, so every gateway service keeps reading
     * config('services.*.base_url') unchanged. Runs at boot (AppServiceProvider).
     */
    public static function applyToConfig(): void
    {
        // Runs at boot — degrade to the env base URL if the settings table isn't
        // there yet (pre-install), mirroring the other boot overlays.
        try {
            foreach (self::GATEWAYS as $gateway => $meta) {
                if ($meta['sandbox_host'] === null) {
                    continue;
                }
                $host = self::mode($gateway) === 'sandbox' ? $meta['sandbox_host'] : $meta['live_host'];
                config(["services.{$gateway}.base_url" => $host]);
            }
        } catch (\Throwable) {
            // Settings unavailable (e.g. pre-migration) — keep the env defaults.
        }
    }

    /** The webhook URL to register on the provider dashboard. */
    public static function webhookUrl(string $gateway): string
    {
        return url('/webhooks/payments/'.$gateway);
    }

    /** The return/callback URL (the wallet page) some gateways need configured. */
    public static function callbackUrl(): string
    {
        return url('/wallet');
    }

    /**
     * A real, read-only connection test with the CURRENT stored keys, so an
     * admin can confirm a gateway works before flipping to live. Bounded
     * timeout. Returns ['ok' => bool, 'message' => string]. Gateways whose test
     * needs a signed request we can't safely fake are reported as manual.
     *
     * @return array{ok: bool, message: string}
     */
    public static function testConnection(string $gateway): array
    {
        if (! self::isGateway($gateway)) {
            return ['ok' => false, 'message' => 'Unknown gateway.'];
        }
        if (! (self::GATEWAYS[$gateway]['testable'] ?? false)) {
            return ['ok' => false, 'message' => 'This gateway signs every request — verify it with a small live test payment instead.'];
        }

        try {
            return match ($gateway) {
                'paystack' => self::ping(
                    Http::withToken((string) config('services.paystack.secret_key'))
                        ->acceptJson()->timeout(8)->connectTimeout(3)
                        ->get(rtrim((string) config('services.paystack.base_url'), '/').'/balance'),
                    'Paystack'),
                'stripe' => self::ping(
                    Http::withToken((string) config('services.stripe.secret_key'))
                        ->timeout(8)->connectTimeout(3)
                        ->get(rtrim((string) config('services.stripe.base_url'), '/').'/balance'),
                    'Stripe'),
                'flutterwave' => self::ping(
                    Http::withToken((string) config('services.flutterwave.secret_key'))
                        ->acceptJson()->timeout(8)->connectTimeout(3)
                        ->get(rtrim((string) config('services.flutterwave.base_url'), '/').'/balances'),
                    'Flutterwave'),
                'nowpayments' => self::ping(
                    Http::withHeaders(['x-api-key' => (string) config('services.nowpayments.api_key')])
                        ->acceptJson()->timeout(8)->connectTimeout(3)
                        ->get(rtrim((string) config('services.nowpayments.base_url'), '/').'/v1/balance'),
                    'NOWPayments'),
                'paypal' => self::pingPaypal(),
                default => ['ok' => false, 'message' => 'No test available.'],
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach the gateway — check the key and try again.'];
        }
    }

    private static function ping(Response $res, string $label): array
    {
        return $res->successful()
            ? ['ok' => true, 'message' => $label.' connected — the key works.']
            : ['ok' => false, 'message' => $label.' rejected the key (HTTP '.$res->status().').'];
    }

    private static function pingPaypal(): array
    {
        $token = Http::withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.client_secret'))
            ->asForm()->acceptJson()->timeout(8)->connectTimeout(3)
            ->post(rtrim((string) config('services.paypal.base_url'), '/').'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->json('access_token');

        return $token
            ? ['ok' => true, 'message' => 'PayPal connected — the credentials work.']
            : ['ok' => false, 'message' => 'PayPal rejected the client id/secret.'];
    }

    public static function isModeKey(string $key): bool
    {
        return str_starts_with($key, self::MODE_PREFIX);
    }
}
