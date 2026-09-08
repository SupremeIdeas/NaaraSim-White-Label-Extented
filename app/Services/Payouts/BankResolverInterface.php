<?php

namespace App\Services\Payouts;

/**
 * Contract for a PSP that can (a) list the banks/networks it supports for a
 * country and (b) resolve an account number to the real holder's name BEFORE we
 * ever save it as a payout destination — the "money never goes to a typo" rule
 * (ROADMAP §Layer 0.1). Mirrors PaymentGatewayInterface: gated on key
 * availability so a PSP with no key simply doesn't participate.
 */
interface BankResolverInterface
{
    /** Provider key, e.g. "paystack" / "flutterwave". */
    public function name(): string;

    /** True once the PSP's key is configured (else it can't be used). */
    public function available(): bool;

    /** True if this PSP resolves accounts for the given ISO-3166 country. */
    public function supports(string $country): bool;

    /**
     * Banks/networks this PSP offers for a country, for the account picker.
     *
     * @return list<array{code: string, name: string}>
     */
    public function banks(string $country): array;

    /**
     * Resolve an account number to its holder name, or null if the PSP can't
     * confirm it (never guess — an unconfirmable account is not saveable).
     */
    public function resolve(string $country, string $bankCode, string $accountNumber): ?ResolvedAccount;
}
