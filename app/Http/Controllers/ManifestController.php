<?php

namespace App\Http\Controllers;

use App\Support\AppExport;

/**
 * Dynamic PWA manifest (App Export §1). Served from Setting-backed config so the
 * admin can rename the app / swap the icon / change theme colours with no
 * deploy. Cached at the CDN/browser for a short while.
 */
class ManifestController extends Controller
{
    public function __invoke()
    {
        return response()
            ->json(AppExport::manifest(), 200, [], JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
