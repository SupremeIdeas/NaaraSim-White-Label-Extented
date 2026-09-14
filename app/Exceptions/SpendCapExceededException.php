<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a group-plan purchase would take a member's cumulative spend
 * past the cap the group owner set for them (Prompt 11 §3). Distinct from
 * InsufficientBalanceException — this is a cap the OWNER chose, not a wallet
 * that genuinely can't cover it.
 */
class SpendCapExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $currency,
        public readonly float $requested,
        public readonly float $alreadySpent,
        public readonly float $cap,
    ) {
        parent::__construct(
            "Wallet-group spend cap exceeded for member {$memberId}: {$currency} {$requested} requested, ".
            "{$alreadySpent} already spent against a {$cap} cap."
        );
    }
}
