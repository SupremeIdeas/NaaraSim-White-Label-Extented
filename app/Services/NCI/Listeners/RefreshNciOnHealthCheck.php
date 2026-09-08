<?php

namespace App\Services\NCI\Listeners;

use App\Events\HealthCheckCompleted;
use App\Services\NCI\NciScorer;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * NAARA-BUILD-16 §1 — a periodic (15-min health-tick) nudge so NCI's confidence
 * and risk stay fresh between the daily nci:recompute passes. Queued — the health
 * check itself never waits on NCI.
 */
class RefreshNciOnHealthCheck implements ShouldQueue
{
    public function __construct(private NciScorer $scorer) {}

    public function handle(HealthCheckCompleted $event): void
    {
        foreach ($event->providerKeys as $key) {
            $this->scorer->recompute($key);
        }
    }
}
