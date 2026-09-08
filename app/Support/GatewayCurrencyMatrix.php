<?php

namespace App\Support;

use App\Services\Pricing\CurrencyService;

/**
 * Unified USD Wallet blueprint, Part B §3.6 — the missing source of truth for
 * which currencies a top-up gateway actually accepts. Before this, `Wallet.php`
 * let a user pick ANY gateway with ANY currency (e.g. Stripe + NGN, Payssion +
 * EUR) with no cross-validation — an unsupported pairing either fails silently
 * at the provider or produces an amount CurrencyService/CreditWalletJob can't
 * cleanly resolve.
 *
 * Currency lists are restricted to CurrencyService::SUPPORTED (the currencies
 * this platform actually models/displays) and sourced from each provider's own
 * public documentation — but this is a FIRST DRAFT to verify against each
 * provider's live dashboard before treating it as final, exactly as flagged in
 * the blueprint. Countries are informational only (used only where this class
 * is asked to suggest a gateway for a known country) — they never hard-filter
 * what a user can select.
 */
class GatewayCurrencyMatrix
{
    /**
     * gateway => [currencies this gateway accepts, countries it's realistically
     * usable in (null = broad/international, card- or crypto-based)].
     *
     * @var array<string, array{currencies: list<string>, countries: ?list<string>}>
     */
    public const MATRIX = [
        'paystack' => ['currencies' => ['NGN', 'GHS', 'KES', 'ZAR', 'USD'], 'countries' => ['NG', 'GH', 'KE', 'ZA']],
        'flutterwave' => ['currencies' => ['NGN', 'GHS', 'KES', 'ZAR', 'USD', 'GBP', 'EUR'], 'countries' => ['NG', 'GH', 'KE', 'ZA']],
        'stripe' => ['currencies' => ['USD', 'GBP', 'EUR', 'CAD', 'INR'], 'countries' => null],
        'paypal' => ['currencies' => ['USD', 'GBP', 'EUR', 'CAD', 'INR'], 'countries' => null],
        'binance' => ['currencies' => ['USD', 'USDT'], 'countries' => null],
        'nowpayments' => ['currencies' => ['USD', 'USDT'], 'countries' => null],
        'cryptomus' => ['currencies' => ['USD', 'USDT'], 'countries' => null],
        'coinpayments' => ['currencies' => ['USD', 'USDT'], 'countries' => null],
        // Payssion aggregates local payment methods per country but always
        // settles/quotes in USD in this integration — never assume a specific
        // local currency without confirming Payssion's actual settlement report.
        'payssion' => ['currencies' => ['USD'], 'countries' => null],
    ];

    /** @return list<string> currency codes this gateway accepts, or [] if unknown. */
    public static function currenciesFor(string $gateway): array
    {
        return self::MATRIX[$gateway]['currencies'] ?? [];
    }

    /** @return list<string> gateway names that accept this currency. */
    public static function gatewaysFor(string $currency): array
    {
        $currency = strtoupper($currency);

        return array_values(array_filter(
            array_keys(self::MATRIX),
            fn (string $gateway) => in_array($currency, self::MATRIX[$gateway]['currencies'], true),
        ));
    }

    public static function supports(string $gateway, string $currency): bool
    {
        return in_array(strtoupper($currency), self::currenciesFor($gateway), true);
    }

    /**
     * The best gateway for a known country: the first gateway (in MATRIX
     * declaration order) whose country list includes it, or null if none
     * declares that country (an international/card gateway still works —
     * this only picks a smarter DEFAULT, it never limits the selector).
     */
    public static function bestGatewayForCountry(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }
        $country = strtoupper($country);
        foreach (self::MATRIX as $gateway => $meta) {
            if ($meta['countries'] !== null && in_array($country, $meta['countries'], true)) {
                return $gateway;
            }
        }

        return null;
    }

    /** Every currency any known gateway accepts, restricted to CurrencyService::SUPPORTED. */
    public static function allCurrencies(): array
    {
        $codes = array_unique(array_merge(...array_values(array_map(fn ($m) => $m['currencies'], self::MATRIX))));

        return array_values(array_filter($codes, fn ($c) => isset(CurrencyService::SUPPORTED[$c])));
    }
}
