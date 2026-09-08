<?php

namespace App\Services\NCI\Listeners;

use App\Services\NCI\NciScorer;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * NAARA-BUILD-16 §1 — a circuit transition (CircuitOpened / CircuitClosed) is a
 * strong reliability signal, so NCI recomputes that one provider's confidence +
 * risk immediately rather than waiting for the daily pass. Queued — never on the
 * request path. Registered for both events; each carries ->providerKey.
 */
class RecomputeProviderScore implements ShouldQueue
{
    public function __construct(private NciScorer $scorer) {}

    public function handle(object $event): void
    {
        if (isset($event->providerKey)) {
            $this->scorer->recompute($event->providerKey);
        }
    }
}
