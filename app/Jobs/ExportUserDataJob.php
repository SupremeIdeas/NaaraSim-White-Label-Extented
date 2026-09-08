<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\UserDataExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Build a user's personal-data export (blueprint Section 26.2 / GDPR) and store
 * it on the PRIVATE disk (Wasabi if configured, else the local disk — never
 * web-accessible). The file is reached only through an owner-authenticated
 * download route. Runs on Horizon, off the request cycle.
 */
class ExportUserDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            return;
        }

        $json = UserDataExporter::toJson($user);
        $path = 'exports/'.$user->id.'/'.Str::uuid().'.json';

        Storage::disk(MediaStorage::privateDisk())->put($path, $json);

        // Replace any previous export so only the latest is downloadable.
        $previous = $user->data_export_path;
        $user->forceFill([
            'data_export_path' => $path,
            'data_export_ready_at' => now(),
        ])->save();

        if ($previous && $previous !== $path) {
            Storage::disk(MediaStorage::privateDisk())->delete($previous);
        }

        Auditor::log('account.export_ready', 'User', $user->id);
    }
}
