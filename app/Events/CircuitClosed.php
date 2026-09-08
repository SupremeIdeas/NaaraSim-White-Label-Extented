<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** NAARA-BUILD-16 §1 — fired by CircuitBreaker when a provider's circuit recovers to CLOSED. */
class CircuitClosed
{
    use Dispatchable;

    public function __construct(public string $providerKey) {}
}
