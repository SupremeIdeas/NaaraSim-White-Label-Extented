<?php

namespace App\Jobs;

use App\Services\Backup\BackupManager;
use App\Support\Auditor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Run a database backup off the request cycle (blueprint rule 1.8 — external/
 * heavy work is queued). Dispatched from the admin Backups panel; runs on
 * Horizon. The archive lands on the configured destination disk.
 */
class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $requestedBy = null)
    {
    }

    public function handle(BackupManager $backups): void
    {
        $backups->runNow();
        Auditor::log('backup.created', 'User', $this->requestedBy);
    }
}
