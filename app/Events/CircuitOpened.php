<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** NAARA-BUILD-16 §1 — fired by CircuitBreaker when a provider's circuit trips OPEN. */
class CircuitOpened
{
    use Dispatchable;

    public function __construct(public string $providerKey) {}
}
