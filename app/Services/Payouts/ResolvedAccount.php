<?php

namespace App\Services\Payouts;

/**
 * The result of resolving a bank/mobile-money account with a PSP: the real
 * account holder's name (used to confirm before any money is saved as a payout
 * destination) plus the resolving provider.
 */
final class ResolvedAccount
{
    public function __construct(
        public readonly string $accountName,
        public readonly string $provider,
        public readonly ?string $bankName = null,
    ) {
    }
}
