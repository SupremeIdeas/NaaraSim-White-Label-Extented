<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature flag for the Developer API (ROADMAP §Layer 2). The whole /api/v1
 * surface is OFF by default and only exists once the admin flips
 * `developer_api.enabled`. When off we return a plain 404 so the API's very
 * existence isn't advertised.
 */
class EnsureDeveloperApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) Setting::getValue('developer_api.enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
