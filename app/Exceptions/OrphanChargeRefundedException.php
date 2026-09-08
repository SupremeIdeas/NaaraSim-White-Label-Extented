<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown after the orphan-charge guard has AUTO-REFUNDED a debit whose
 * downstream delivery failed (money-safety rule 1.2 — never charge without
 * delivering). The original failure is attached as the previous exception.
 */
class OrphanChargeRefundedException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
