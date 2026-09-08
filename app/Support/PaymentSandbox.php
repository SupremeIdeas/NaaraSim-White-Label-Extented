<?php

namespace App\Support;

/**
 * Payment sandbox / test-mode indicator (BUILD-2 §8). There is no platform-wide
 * signal that a gateway is still pointed at its test environment — so an admin
 * could finish testing and forget to flip to Live, or a user could be unsure
 * whether real money is about to move. This detects test mode from each
 * provider's well-known, stable convention (a test-key prefix or a sandbox base
 * URL) and drives an admin-dashboard banner + a customer-facing checkout tag.
 *
 * We only ever report a POSITIVE test-mode detection. Where a provider has no
 * reliable public test-key convention (Binance Pay, Cryptomus, CoinPayments,
 * Payssion), we say nothing rather than falsely imply "Live" — a wrong "you're
 * live" is more dangerous than an absent tag.
 */
class PaymentSandbox
{
    public const LABELS = [
        'paystack' => 'Paystack',
        'flutterwave' => 'Flutterwave',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'binance' => 'Binance Pay',
        'nowpayments' => 'NOWPayments',
        'cryptomus' => 'Cryptomus',
        'coinpayments' => 'CoinPayments',
        'payssion' => 'Payssion',
    ];

    /**
     * Is this specific gateway detected to be in test/sandbox mode? Returns
     * false when the gateway is live OR when test mode can't be told from its
     * config (never a false "test").
     */
    public static function isTest(string $gateway): bool
    {
        $secret = (string) config("services.{$gateway}.secret_key", '');
        $base = (string) config("services.{$gateway}.base_url", '');

        return match ($gateway) {
            // Stripe / Paystack: sk_test_… (live is sk_live_…).
            'stripe', 'paystack' => str_contains($secret, 'sk_test'),
            // Flutterwave v3: FLWSECK_TEST-… (live is FLWSECK-…).
            'flutterwave' => str_contains(strtoupper($secret), '_TEST'),
            // PayPal / NOWPayments: a dedicated sandbox host.
            'paypal', 'nowpayments' => str_contains(strtolower($base), 'sandbox'),
            // No reliable public test convention — don't guess.
            default => false,
        };
    }

    /**
     * Configured gateways currently detected in test/sandbox mode (slug => label).
     *
     * @return array<string, string>
     */
    public static function testGateways(): array
    {
        $out = [];
        foreach (array_keys(self::LABELS) as $gw) {
            if (ProviderStatus::isActive($gw) && self::isTest($gw)) {
                $out[$gw] = self::LABELS[$gw];
            }
        }

        return $out;
    }

    public static function anyTest(): bool
    {
        return self::testGateways() !== [];
    }
}
