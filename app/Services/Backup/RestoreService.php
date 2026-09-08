<?php

namespace App\Services\Backup;

use App\Models\User;
use App\Support\Auditor;
use App\Support\Backup\MysqldumpAvailability;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Restore the database from a backup archive (blueprint Section 28). Guarded to
 * super_admin only. Safety rails: the app is put into maintenance mode, a fresh
 * "safety snapshot" backup is taken BEFORE anything is overwritten, and the app
 * is always brought back up (even if the restore throws).
 */
class RestoreService
{
    public function __construct(protected BackupManager $backups)
    {
    }

    public function restore(User $actor, string $archivePath): void
    {
        abort_unless($actor->hasRole('super_admin'), 403, 'Only a super admin may restore a backup.');
        abort_unless($this->backups->exists($archivePath), 404, 'That backup no longer exists.');

        Artisan::call('down', ['--retry' => 60]);

        try {
            // Snapshot-first: never restore without a rollback point.
            $this->backups->runNow();

            $this->importArchive($archivePath);

            Auditor::log('backup.restored', 'User', $actor->id, ['archive' => basename($archivePath)]);
        } finally {
            Artisan::call('up');
        }
    }

    /**
     * Download the archive, extract it (with the configured password), and load
     * the dump back into the database. SQLite restores by replacing the file;
     * MySQL replays the .sql through PDO so it works even without the mysql
     * client binary.
     *
     * Public so the Updater's UpdateApplier (Batch 2) can restore the database
     * from the specific pre-apply snapshot it recorded, WITHOUT re-entering
     * maintenance mode or taking a second safety snapshot the way restore()
     * does — the apply pipeline already owns maintenance mode and already took
     * its snapshot, so it needs just the DB-import step, not the full
     * admin-driven restore wrapper. Behaviour of restore() is unchanged.
     */
    public function importArchive(string $archivePath): void
    {
        $work = storage_path('app/backup-temp/restore-'.Str::uuid());
        File::ensureDirectoryExists($work);

        try {
            $local = $work.'/'.basename($archivePath);
            // Reads from the dedicated backup disk directly (not MediaStorage):
            // restore's whole job is pulling an archive off the backup destination,
            // so it must address that named disk, not the upload-routing logic.
            File::put($local, Storage::disk($this->backups->disk())->get($archivePath));

            $zip = new ZipArchive;
            if ($zip->open($local) !== true) {
                abort(422, 'Could not open the backup archive.');
            }
            if ($password = config('backup.backup.password')) {
                $zip->setPassword($password);
            }
            $zip->extractTo($work);
            $zip->close();

            $dump = collect(File::allFiles($work))
                ->first(fn ($f) => in_array($f->getExtension(), ['sql', 'sqlite'], true));

            abort_if($dump === null, 422, 'No database dump found in the archive.');

            if ($dump->getExtension() === 'sqlite') {
                File::copy($dump->getRealPath(), database_path(basename(config('database.connections.sqlite.database'))));
            } else {
                DB::unprepared(File::get($dump->getRealPath()));
            }
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** Surfaced in the UI so the operator knows which dump path is in play. */
    public function dumpEngine(): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return 'pure-PHP SQLite (no binary needed)';
        }

        return MysqldumpAvailability::available() ? 'mysqldump' : 'ifsnop/mysqldump-php (PHP fallback)';
    }
}
