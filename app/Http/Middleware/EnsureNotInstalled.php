<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /install wizard: once the app is installed the lock file exists
 * and the installer is closed (redirect to login).
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installer::isInstalled()) {
            return redirect('/login');
        }

        return $next($request);
    }
}
