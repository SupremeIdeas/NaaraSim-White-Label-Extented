<?php

namespace App\Livewire\Admin;

use App\Jobs\RunBackupJob;
use App\Services\Backup\BackupManager;
use App\Services\Backup\ConfigBackup;
use App\Services\Backup\DatasetService;
use App\Services\Backup\RestoreService;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Backups (blueprint Section 28). Super-admin only: run/download/delete
 * database backups, restore from an archive (maintenance mode + snapshot-first),
 * and export/import portable reference datasets with a mandatory dry-run.
 */
#[Layout('components.layouts.admin')]
class Backups extends Component
{
    use WithFileUploads;

    public ?string $status = null;

    public ?string $error = null;

    /** Dataset export selection + import staging. */
    public array $exportTables = [];

    public $datasetFile;

    public ?array $dryRun = null;

    public string $pendingImportJson = '';

    /** Portable configuration backup. */
    public string $cfgPassphrase = '';

    public string $cfgRestorePassphrase = '';

    public $cfgFile;

    public ?array $cfgPreview = null;

    public string $cfgPendingFile = '';

    public function mount(): void
    {
        // Backups touch every table + can overwrite the database — super only.
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
    }

    public function backupNow(): void
    {
        RunBackupJob::dispatch(Auth::id());
        $this->status = 'Backup started — it runs in the background and will appear in the list when finished.';
    }

    public function download(string $path, BackupManager $backups)
    {
        $this->assertOwnedBackup($path, $backups);

        return $backups->download($path);
    }

    public function deleteBackup(string $path, BackupManager $backups): void
    {
        $this->assertOwnedBackup($path, $backups);
        $backups->delete($path);
        Auditor::log('backup.deleted', null, null, ['archive' => basename($path)]);
        $this->status = 'Backup deleted.';
    }

    public function restore(string $path, RestoreService $restore, BackupManager $backups): void
    {
        $this->assertOwnedBackup($path, $backups);
        $restore->restore(Auth::user(), $path);
        $this->status = 'Database restored from '.basename($path).'.';
    }

    public function exportDataset(DatasetService $datasets)
    {
        $json = $datasets->export($this->exportTables);

        return response()->streamDownload(
            fn () => print ($json),
            'naarasim-dataset-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    /** Step 1 of import: analyse without writing (mandatory dry-run). */
    public function analyzeImport(DatasetService $datasets): void
    {
        $this->error = null;
        $this->validate(['datasetFile' => 'required|file|mimes:json,txt|max:10240']);

        try {
            $json = $this->datasetFile->get();
            $this->dryRun = $datasets->import($json, dryRun: true);
            $this->pendingImportJson = $json;
        } catch (\Throwable $e) {
            $this->error = 'Could not read that dataset: '.$e->getMessage();
            $this->dryRun = null;
        }
    }

    /** Step 2 of import: commit inside a transaction. */
    public function commitImport(DatasetService $datasets): void
    {
        if ($this->pendingImportJson === '') {
            return;
        }

        $summary = $datasets->import($this->pendingImportJson, dryRun: false);
        $inserted = array_sum(array_column($summary, 'new'));

        $this->reset('datasetFile', 'dryRun', 'pendingImportJson');
        $this->status = "Dataset imported — {$inserted} new row(s) added.";
    }

    public function exportConfig(ConfigBackup $config)
    {
        $this->error = null;
        try {
            $file = $config->export($this->cfgPassphrase);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return null;
        }
        Auditor::log('backup.config_exported');
        $this->cfgPassphrase = '';

        return response()->streamDownload(fn () => print ($file), 'naara-config-'.now()->format('Ymd-His').'.naaracfg', ['Content-Type' => 'application/json']);
    }

    public function previewConfig(ConfigBackup $config): void
    {
        $this->error = null;
        $this->cfgPreview = null;
        $this->validate(['cfgFile' => 'required|file|max:51200']);
        try {
            $file = $this->cfgFile->get();
            $this->cfgPreview = $config->preview($file, $this->cfgRestorePassphrase);
            $this->cfgPendingFile = $file;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function restoreConfig(ConfigBackup $config): void
    {
        $this->error = null;
        if ($this->cfgPendingFile === '') {
            return;
        }
        try {
            $r = $config->restore($this->cfgPendingFile, $this->cfgRestorePassphrase);
        } catch (\Throwable $e) {
            $this->error = 'Nothing was changed. '.$e->getMessage();

            return;
        }
        $this->reset('cfgFile', 'cfgPreview', 'cfgPendingFile', 'cfgRestorePassphrase');
        $this->status = 'Configuration restored: '.$r['settings'].' settings and '.array_sum($r['tables']).' content rows.';
    }

    private function assertOwnedBackup(string $path, BackupManager $backups): void
    {
        abort_unless(str_starts_with($path, $backups->directory().'/'), 403);
    }

    public function render()
    {
        return view('livewire.admin.backups', [
            'backups' => app(BackupManager::class)->list(),
            'disk' => app(BackupManager::class)->disk(),
            'engine' => app(RestoreService::class)->dumpEngine(),
            'exportable' => DatasetService::exportableTables(),
            'cfgTables' => ConfigBackup::tables(),
        ]);
    }
}
