<?php

namespace App\Console\Commands;

use App\Services\NCI\NciScorer;
use Illuminate\Console\Command;

/**
 * NAARA-BUILD-16 §3.2 — the daily full recompute of every provider's NCI score,
 * confidence and risk over the trailing window. Scheduled (once daily by
 * default), withoutOverlapping + runInBackground per the scheduler-tuning
 * discipline. Purely observational: it reads outcomes and writes NCI columns —
 * never a live routing decision.
 */
class NciRecomputeCommand extends Command
{
    protected $signature = 'nci:recompute';

    protected $description = 'Recompute NCI reliability score, confidence and risk for every provider.';

    public function handle(NciScorer $scorer): int
    {
        $scorer->recomputeAll();
        $this->info('NCI scores recomputed.');

        return self::SUCCESS;
    }
}
