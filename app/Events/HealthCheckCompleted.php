<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * NAARA-BUILD-16 §1 — fired at the end of ProviderHealth::checkAll(). NCI uses it
 * as a periodic nudge to refresh confidence/risk between daily recomputes.
 */
class HealthCheckCompleted
{
    use Dispatchable;

    /** @param list<string> $providerKeys */
    public function __construct(public array $providerKeys) {}
}
