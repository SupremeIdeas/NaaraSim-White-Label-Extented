<?php

namespace App\Jobs;

use App\Models\PlatformUpdateAttempt;
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
 *
 * White-label subscribers only (Batch 5 §4): if this apply came from a package
 * with a known package_id, and this instance has the (fork-only)
 * WhiteLabelUpdateClient class available, report the outcome back to the
 * original platform once the apply finishes — closes the oversight loop
 * regardless of whether the package arrived via the pull flow or a manual
 * upload. `class_exists()` is a file-presence check, not a container binding,
 * so this is a true no-op on the master platform (that class is never added
 * there) without needing any master-side change or shared-engine branch.
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
            $attempt = $applier->apply($this->packagePath, $this->initiatedBy);
            $this->reportToOriginalPlatformIfWhiteLabel($attempt);
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

    private function reportToOriginalPlatformIfWhiteLabel(PlatformUpdateAttempt $attempt): void
    {
        if (! class_exists(\App\Services\Updater\WhiteLabelUpdateClient::class)) {
            return;
        }

        $status = match ($attempt->status) {
            PlatformUpdateAttempt::STATUS_SUCCEEDED => 'applied',
            PlatformUpdateAttempt::STATUS_ROLLED_BACK => 'rolled_back',
            default => 'failed',
        };

        app(\App\Services\Updater\WhiteLabelUpdateClient::class)->reportOutcome($attempt->package_id, $status, array_filter([
            'downtime_seconds' => $attempt->downtime_seconds,
            'applied_at' => now()->toIso8601String(),
            'notes' => $attempt->failure_reason,
        ], fn ($v) => $v !== null));
    }
}
