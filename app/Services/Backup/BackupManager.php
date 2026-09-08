<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin wrapper over spatie/laravel-backup for the admin panel (blueprint
 * Section 28): run a database backup, and list/download/delete the archives on
 * the configured destination disk (Wasabi if set, else the local disk).
 */
class BackupManager
{
    /**
     * The destination disk configured in config/backup.php.
     *
     * Deliberately NOT routed through MediaStorage: MediaStorage picks the best
     * disk for a user *upload*, whereas a backup must land on its own dedicated
     * backup destination (Wasabi/local per config/backup.php) — a different
     * concern. Every Storage::disk() call in this class + RestoreService uses
     * this one named disk on purpose; that's the documented exception.
     */
    public function disk(): string
    {
        return config('backup.backup.destination.disks')[0] ?? 'local';
    }

    /** The folder the archives live in (spatie uses the backup name). */
    public function directory(): string
    {
        return (string) config('backup.backup.name', config('app.name', 'NaaraSim'));
    }

    /** Run a database backup now (synchronous — call from a queued job). */
    public function runNow(): void
    {
        Artisan::call('backup:run', ['--only-db' => true]);
    }

    /**
     * The archives on the destination disk, newest first.
     *
     * @return list<array{path:string,name:string,size:int,last_modified:int}>
     */
    public function list(): array
    {
        $disk = Storage::disk($this->disk());
        $dir = $this->directory();

        if (! $disk->exists($dir)) {
            return [];
        }

        $files = collect($disk->files($dir))
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => [
                'path' => $p,
                'name' => basename($p),
                'size' => $disk->size($p),
                'last_modified' => $disk->lastModified($p),
            ])
            ->sortByDesc('last_modified')
            ->values()
            ->all();

        return $files;
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk())->exists($path);
    }

    public function download(string $path): StreamedResponse
    {
        return Storage::disk($this->disk())->download($path, basename($path));
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk())->delete($path);
    }
}
