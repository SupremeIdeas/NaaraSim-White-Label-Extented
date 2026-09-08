<?php

namespace App\Services\Credits;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public int $userId, public float $needed, public float $available)
    {
        parent::__construct("User {$userId} has {$available} credits, needs {$needed}.");
    }
}
