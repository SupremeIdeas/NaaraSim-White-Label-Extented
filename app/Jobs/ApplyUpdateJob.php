<?php

namespace App\Jobs;

use App\Services\Updater\UpdateApplier;
use App\Support\Auditor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Apply an update package off the request cycle (Batch 2 §4). An apply involves
 * a DB backup and migrations and can run long, so it must never be tied to a web
 * request's timeout. The admin page polls the resulting platform_update_attempts
 * row for live status.
 *
 * Never blind-retries (money/ops-safety rule 7): a half-applied update that
 * failed has already rolled itself back inside UpdateApplier — re-running it
 * automatically would be exactly the wrong thing.
 */
class ApplyUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public string $packagePath,
        public ?int $initiatedBy = null,
    ) {
    }

    public function handle(UpdateApplier $applier): void
    {
        try {
            $applier->apply($this->packagePath, $this->initiatedBy);
        } catch (\Throwable $e) {
            // Pre-apply refusals (bad signature, incompatible version, lock held,
            // no disk) throw before anything was touched; UpdateApplier has
            // already audited the specific reason. Record the job-level outcome
            // too so the admin sees why nothing happened.
            Auditor::log('update.apply_failed', null, null, ['reason' => $e->getMessage()]);
        } finally {
            // The uploaded package was a one-shot artifact — don't leave it on disk.
            if (is_file($this->packagePath)) {
                @unlink($this->packagePath);
            }
        }
    }
}
