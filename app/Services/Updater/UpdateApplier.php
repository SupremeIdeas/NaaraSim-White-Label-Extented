<?php

namespace App\Services\Updater;

use App\Jobs\AlertAdminJob;
use App\Models\PlatformUpdateAttempt;
use App\Models\Setting;
use App\Services\Backup\BackupManager;
use App\Services\Backup\RestoreService;
use App\Support\Auditor;
use App\Support\EnvironmentGuard;
use App\Support\SchedulerHealth;
use App\Support\UpdateManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Applies a verified `.naaraupdate` package to a running instance (Batch 2).
 *
 * This is the single class both the master's manual-upload flow and Batch 5's
 * white-label pull flow call — one implementation, two front doors. The design
 * principle throughout: every step is reversible until an AUTOMATED health check
 * proves it safe. If anything fails, the platform is returned — files AND
 * database — to exactly the state it was in before, then brought back up on the
 * old, known-good version.
 *
 * The `Setting` key `platform.version` holds the current applied version; the
 * `platform_update_attempts` table records every attempt and its outcome.
 */
class UpdateApplier
{
    /** Key under which the current applied platform version is stored. */
    public const VERSION_SETTING = 'platform.version';

    /** Cross-process guard so two applies can never overlap (WalletService pattern). */
    public const LOCK_KEY = 'update:applying';

    private string $applicationRoot;

    public function __construct(
        private readonly PackageVerifier $verifier,
        private readonly BackupManager $backups,
        private readonly RestoreService $restore,
    ) {
        $this->applicationRoot = base_path();
    }

    /** Point the applier at a different app tree (tests never touch the real one). */
    public function forApplicationRoot(string $root): static
    {
        $this->applicationRoot = rtrim($root, '/');

        return $this;
    }

    /**
     * Run the full apply pipeline. Returns the recorded attempt (succeeded,
     * rolled_back, or failed_unrecoverable). Throws only for the pre-apply
     * refusals that never touched anything (bad signature, incompatible
     * version, another apply in progress, no disk) — where there is nothing to
     * roll back and nothing partial was done.
     */
    public function apply(string $packagePath, ?int $initiatedByUserId = null): PlatformUpdateAttempt
    {
        $lock = Cache::lock(self::LOCK_KEY, 1800);
        if (! $lock->get()) {
            Auditor::log('update.rejected', null, null, ['reason' => 'another apply is already in progress']);
            throw new \RuntimeException('Another update is already being applied. Try again once it finishes.');
        }

        try {
            return $this->runPipeline($packagePath, $initiatedByUserId);
        } finally {
            optional($lock)->release();
        }
    }

    private function runPipeline(string $packagePath, ?int $initiatedByUserId): PlatformUpdateAttempt
    {
        // --- Step 1: verify (reuse Batch 1) ---
        // Pass this instance's own product line so a package built for a
        // different one (e.g. a master-tagged package on a white-label fork)
        // is rejected here even if it somehow got this far — the actual local
        // trust gate, not just the distribution browse filter (Sept-14 audit B1).
        $result = $this->verifier->verify($packagePath, config('updater.product_identifier'));
        if (! $result->passed) {
            Auditor::log('update.rejected', null, null, ['reason' => $result->reason]);
            throw new \RuntimeException('Package rejected: '.$result->reason);
        }
        $manifest = $result->manifest;

        // --- Step 2: compatibility ---
        $currentVersion = Setting::getValue(self::VERSION_SETTING);
        if ($currentVersion !== null
            && UpdateManifest::isValidVersion((string) $currentVersion)
            && UpdateManifest::compareVersions((string) $currentVersion, $manifest->minCompatibleVersion) < 0) {
            Auditor::log('update.rejected', null, null, [
                'reason' => 'incompatible', 'current' => $currentVersion, 'requires' => $manifest->minCompatibleVersion,
            ]);
            throw new \RuntimeException(
                "This instance is on {$currentVersion}, but this update requires {$manifest->minCompatibleVersion} or later — apply the intermediate update first."
            );
        }

        $attempt = PlatformUpdateAttempt::create([
            'package_id' => $manifest->packageId,
            'from_version' => $currentVersion !== null ? (string) $currentVersion : null,
            'to_version' => $manifest->version,
            'status' => PlatformUpdateAttempt::STATUS_APPLYING,
            'initiated_by_user_id' => $initiatedByUserId,
        ]);

        // --- Step 3: pre-flight (nothing applied yet; a failure here just aborts) ---
        $this->preflight($packagePath, $attempt);

        // --- Step 4: snapshot (DB + files) ---
        $this->backups->runNow();
        $archive = $this->backups->list()[0]['path'] ?? null;
        $attempt->update(['backup_archive_path' => $archive]);

        $snapshotDir = storage_path('app/update-snapshots/'.$attempt->id);
        $touched = $this->snapshotFiles($manifest, $snapshotDir);

        // --- Step 5: maintenance mode ---
        $secret = Str::random(32);
        Artisan::call('down', ['--retry' => 60, '--secret' => $secret]);
        $attempt->update(['maintenance_started_at' => now()]);

        try {
            // --- Step 6: apply files ---
            $filesChanged = $this->applyFiles($packagePath, $manifest);

            // --- Step 7: run migrations (scoped to exactly this package's) ---
            $migrationsRun = $this->runMigrations($manifest);

            // --- Step 8: health-check gate ---
            $health = $this->healthCheck();

            if (! ($health['passed'] ?? false)) {
                return $this->rollback($attempt, $manifest, $touched, $snapshotDir, $archive,
                    'Post-apply health check failed.', $health);
            }

            // --- Step 9: success ---
            Artisan::call('up');
            Setting::setValue(self::VERSION_SETTING, $manifest->version, 'platform');

            $attempt->update([
                'status' => PlatformUpdateAttempt::STATUS_SUCCEEDED,
                'files_changed_count' => $filesChanged,
                'migrations_run_count' => $migrationsRun,
                'health_check_result' => $health,
                'maintenance_ended_at' => now(),
                'downtime_seconds' => $this->downtime($attempt),
            ]);
            $this->cleanupSnapshot($snapshotDir);

            Auditor::log('update.applied', 'PlatformUpdateAttempt', $attempt->id, [
                'from' => $attempt->from_version, 'to' => $manifest->version,
                'files' => $filesChanged, 'migrations' => $migrationsRun,
            ]);

            return $attempt->fresh();
        } catch (\Throwable $e) {
            return $this->rollback($attempt, $manifest, $touched, $snapshotDir, $archive,
                'Apply threw: '.$e->getMessage(), null);
        }
    }

    /**
     * Restore the platform — files then database — to exactly its pre-apply
     * state, then bring it back up on the old version. A DB-restore failure is
     * the one true worst case: the app is left DOWN deliberately (safer than
     * serving a half-restored app) behind the loudest possible alert.
     *
     * @param  array<int,string>  $touched  every repo-relative path we wrote or deleted
     * @param  array<string,mixed>|null  $health
     */
    private function rollback(
        PlatformUpdateAttempt $attempt,
        UpdateManifest $manifest,
        array $touched,
        string $snapshotDir,
        ?string $archive,
        string $reason,
        ?array $health,
    ): PlatformUpdateAttempt {
        // 1. Files — restore what existed, delete what we newly created.
        $this->rollbackFiles($touched, $snapshotDir);

        // 2. Database — restore from the exact pre-apply snapshot.
        if ($archive !== null) {
            try {
                $this->restore->importArchive($archive);
            } catch (\Throwable $dbError) {
                $attempt->update([
                    'status' => PlatformUpdateAttempt::STATUS_FAILED_UNRECOVERABLE,
                    'failure_reason' => $reason.' | ROLLBACK DB RESTORE FAILED: '.$dbError->getMessage(),
                    'health_check_result' => $health,
                    'maintenance_ended_at' => now(),
                    'downtime_seconds' => $this->downtime($attempt),
                ]);
                // Deliberately DO NOT bring the app up — a half-restored DB must
                // not serve real users. Loudest possible alert, manual steps.
                Auditor::log('update.failed_unrecoverable', 'PlatformUpdateAttempt', $attempt->id, ['reason' => $reason]);
                AlertAdminJob::dispatch(
                    'update.failed_unrecoverable',
                    'An update rollback FAILED to restore the database. The platform is in maintenance mode and needs manual restore from backup: '.($archive ?? 'unknown'),
                    ['attempt_id' => $attempt->id, 'archive' => $archive],
                    'critical',
                );

                return $attempt->fresh();
            }
        }

        // 3. Back up on the old, known-good version.
        Artisan::call('up');

        $attempt->update([
            'status' => PlatformUpdateAttempt::STATUS_ROLLED_BACK,
            'failure_reason' => $reason,
            'health_check_result' => $health,
            'maintenance_ended_at' => now(),
            'downtime_seconds' => $this->downtime($attempt),
        ]);
        $this->cleanupSnapshot($snapshotDir);

        Auditor::log('update.rolled_back', 'PlatformUpdateAttempt', $attempt->id, [
            'reason' => $reason, 'to_version_attempted' => $manifest->version,
        ]);
        AlertAdminJob::dispatch(
            'update.rolled_back',
            "Update to {$manifest->version} was rolled back and the platform self-healed to {$attempt->from_version}. Reason: {$reason}",
            ['attempt_id' => $attempt->id],
            'critical',
        );

        return $attempt->fresh();
    }

    /** Disk-space guard: refuse before touching anything if room is tight. */
    private function preflight(string $packagePath, PlatformUpdateAttempt $attempt): void
    {
        $packageSize = (int) @filesize($packagePath);
        $free = (int) @disk_free_space($this->applicationRoot);
        // Payload + a DB backup both need room; require a sane multiple.
        $needed = ($packageSize * 3) + (50 * 1024 * 1024);

        if ($free > 0 && $free < $needed) {
            $attempt->update([
                'status' => PlatformUpdateAttempt::STATUS_FAILED_UNRECOVERABLE,
                'failure_reason' => "Insufficient disk space: {$free} bytes free, ~{$needed} needed.",
            ]);
            throw new \RuntimeException('Not enough disk space to apply this update safely.');
        }
    }

    /**
     * Copy every file we're about to touch (add/modify targets that already
     * exist, and every deletion target) into a snapshot dir keyed to this
     * attempt. Returns the full set of touched repo-relative paths so rollback
     * knows what to restore-or-delete.
     *
     * @return array<int,string>
     */
    private function snapshotFiles(UpdateManifest $manifest, string $snapshotDir): array
    {
        File::ensureDirectoryExists($snapshotDir);
        $touched = [];

        $paths = array_merge(
            array_map(fn ($f) => $f['path'], array_filter($manifest->files, fn ($f) => ($f['action'] ?? 'add') !== 'delete')),
            $manifest->deletions,
        );

        foreach (array_unique($paths) as $rel) {
            $touched[] = $rel;
            $current = $this->applicationRoot.'/'.$rel;
            if (is_file($current)) {
                $dest = $snapshotDir.'/'.$rel;
                File::ensureDirectoryExists(dirname($dest));
                File::copy($current, $dest);
            }
            // If it doesn't exist now, it's a newly-created file → rollback deletes it.
        }

        return $touched;
    }

    private function applyFiles(string $packagePath, UpdateManifest $manifest): int
    {
        $zip = new ZipArchive;
        if ($zip->open($packagePath) !== true) {
            throw new \RuntimeException('Could not reopen verified package during apply.');
        }

        try {
            $changed = 0;

            foreach ($manifest->files as $entry) {
                if (($entry['action'] ?? 'add') === 'delete') {
                    continue;
                }
                $rel = $entry['path'];
                $bytes = $zip->getFromName(PackageVerifier::PAYLOAD_PREFIX.$rel);
                if ($bytes === false) {
                    throw new \RuntimeException("Payload for {$rel} vanished mid-apply.");
                }
                $target = $this->applicationRoot.'/'.$rel;
                if (is_dir($target)) {
                    throw new \RuntimeException("Cannot write {$rel}: a directory exists at that path.");
                }
                File::ensureDirectoryExists(dirname($target));
                // File::put returns false (it does not throw) on a failed write —
                // a mid-apply failure must go straight to rollback, never be
                // silently swallowed and treated as applied (Batch 2 §6).
                if (File::put($target, $bytes) === false) {
                    throw new \RuntimeException("Failed to write {$rel} during apply.");
                }
                $changed++;
            }

            foreach ($manifest->deletions as $rel) {
                $target = $this->applicationRoot.'/'.$rel;
                if (is_file($target)) {
                    File::delete($target);
                    $changed++;
                }
            }

            return $changed;
        } finally {
            $zip->close();
        }
    }

    /** Run exactly this package's migrations, by absolute file path. */
    private function runMigrations(UpdateManifest $manifest): int
    {
        if ($manifest->migrations === []) {
            return 0;
        }

        $paths = [];
        foreach ($manifest->migrations as $name) {
            $paths[] = $this->applicationRoot.'/database/migrations/'.$name;
        }

        Artisan::call('migrate', ['--force' => true, '--path' => $paths, '--realpath' => true]);

        return count($paths);
    }

    /**
     * A single automated pass/fail signal for "is the app fundamentally sane
     * post-apply". Gates on the things a bad file/migration would actually
     * break: the DB connection, the most important tables being queryable, and
     * the core money-path service classes still resolving/instantiating. Env and
     * scheduler state are recorded as advisory context but NOT gated on — they
     * reflect pre-existing configuration a rollback wouldn't fix, so failing on
     * them would cause false rollbacks.
     *
     * @return array<string,mixed>
     */
    private function healthCheck(): array
    {
        $checks = [];

        $checks['database'] = $this->safeCheck(function () {
            DB::connection()->getPdo();
            foreach (['users', 'user_wallets', 'esim_orders'] as $table) {
                DB::table($table)->limit(1)->count();
            }
        });

        $checks['core_services'] = $this->safeCheck(function () {
            // If a payload file introduced a fatal, resolving these throws.
            app(\App\Services\Pricing\PricingEngine::class);
            app(\App\Services\Wallet\WalletService::class);
        });

        $checks['advisory'] = [
            'env_warnings' => EnvironmentGuard::warnings(),
            'queue_is_sync' => SchedulerHealth::queueIsSync(),
        ];

        $checks['passed'] = ($checks['database'] === true) && ($checks['core_services'] === true);

        return $checks;
    }

    /** Run a check closure; true on success, the error message on failure. */
    private function safeCheck(callable $check): bool|string
    {
        try {
            $check();

            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param  array<int,string>  $touched
     */
    private function rollbackFiles(array $touched, string $snapshotDir): void
    {
        foreach ($touched as $rel) {
            $target = $this->applicationRoot.'/'.$rel;
            $backup = $snapshotDir.'/'.$rel;

            if (is_file($backup)) {
                File::ensureDirectoryExists(dirname($target));
                File::copy($backup, $target);
            } elseif (is_file($target)) {
                // No snapshot means we created it — undo by removing it.
                File::delete($target);
            }
        }
    }

    private function cleanupSnapshot(string $snapshotDir): void
    {
        File::deleteDirectory($snapshotDir);
    }

    private function downtime(PlatformUpdateAttempt $attempt): ?int
    {
        if ($attempt->maintenance_started_at === null) {
            return null;
        }

        return max(0, now()->diffInSeconds($attempt->maintenance_started_at));
    }
}
