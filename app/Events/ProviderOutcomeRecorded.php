<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * NAARA-BUILD-16 §1 — fired immediately after CircuitBreaker records a real
 * provider attempt (the synchronous DB write still happens first). NCI's queued
 * listeners learn from it; nothing here runs on the request path.
 */
class ProviderOutcomeRecorded
{
    use Dispatchable;

    public function __construct(
        public string $providerKey,
        public string $stack,
        public string $outcome,        // success | failure
        public ?string $errorCode,
        public \Illuminate\Support\Carbon $occurredAt,
    ) {}
}
