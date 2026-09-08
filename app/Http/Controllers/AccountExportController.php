<?php

namespace App\Http\Controllers;

use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the authenticated user's OWN data export (blueprint Section 26.2).
 * The file lives on the private disk; this route is the only way to reach it,
 * and it always serves the current user's file — never anyone else's.
 */
class AccountExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $user = request()->user();
        $path = $user->data_export_path;

        abort_if(! $path, 404);

        $disk = Storage::disk(MediaStorage::privateDisk());
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, 'naarasim-data-export.json', [
            'Content-Type' => 'application/json',
        ]);
    }
}
