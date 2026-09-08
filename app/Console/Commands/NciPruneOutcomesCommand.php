<?php

namespace App\Console\Commands;

use App\Models\ProviderOutcome;
use Illuminate\Console\Command;

/**
 * NAARA-BUILD-19 §4 — bound the provider_outcomes table. Every real transaction
 * writes a row (CircuitBreaker::record), so on a busy install this table grows
 * without limit and eventually drags every routing read. NCI only ever scores
 * over a 30-day window and the circuit breaker over a 24h one, so anything past
 * the retention horizon is dead weight: this prunes it weekly.
 *
 * Retention is generous (90 days by default, admin-tunable) so a month-boundary
 * recompute still sees a full window plus slack. Deletes in chunks so a large
 * backlog never locks the table, and runs withoutOverlapping in the scheduler.
 */
class NciPruneOutcomesCommand extends Command
{
    protected $signature = 'nci:prune-outcomes {--days= : Override the retention window (days)}';

    protected $description = 'Delete provider_outcomes rows older than the retention window (default 90 days).';

    public function handle(): int
    {
        $days = (int) ($this->option('days')
            ?: \App\Models\Setting::getValue('nci.outcome_retention_days', 90));
        $days = max(1, $days);

        $cutoff = now()->subDays($days);
        $deleted = 0;

        // Chunked delete so a huge backlog never holds a long table lock.
        do {
            $batch = ProviderOutcome::where('occurred_at', '<', $cutoff)
                ->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} provider_outcomes rows older than {$days} days.");

        return self::SUCCESS;
    }
}
