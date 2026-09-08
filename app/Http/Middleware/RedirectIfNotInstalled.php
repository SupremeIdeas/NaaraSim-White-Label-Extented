<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a fresh upload (no lock file yet), send the operator to the web installer
 * — except for the installer itself and inbound webhooks. This is what makes
 * NaaraSim install "like a CodeCanyon product": upload, visit the site, get
 * the wizard.
 */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Installer::isInstalled()
            && ! $request->is('install', 'install/*', 'webhooks/*', 'up')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
