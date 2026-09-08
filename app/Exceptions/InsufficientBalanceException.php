<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a debit would take a wallet balance below zero. A money action
 * that cannot be afforded must fail loudly — never silently overdraw.
 */
class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly string $currency,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(
            "Insufficient {$currency} balance for user {$userId}: requested {$requested}, available {$available}."
        );
    }
}
