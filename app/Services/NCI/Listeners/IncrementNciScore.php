<?php

namespace App\Services\NCI\Listeners;

use App\Events\ProviderOutcomeRecorded;
use App\Services\NCI\NciScorer;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * NAARA-BUILD-16 §3.1 — the per-outcome EMA nudge. ShouldQueue is mandatory: a
 * synchronous listener would run NCI computation inside the customer's purchase
 * request, quietly violating the non-negotiable boundary. Queued, it runs after
 * the response has already returned.
 */
class IncrementNciScore implements ShouldQueue
{
    public function __construct(private NciScorer $scorer) {}

    public function handle(ProviderOutcomeRecorded $event): void
    {
        $this->scorer->applyOutcome($event->providerKey, $event->outcome === 'success');
    }
}
