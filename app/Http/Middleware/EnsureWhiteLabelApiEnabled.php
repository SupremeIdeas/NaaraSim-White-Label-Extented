<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature flag for the white-label distribution API (Batch 4 §3), mirroring
 * EnsureDeveloperApiEnabled exactly. The whole /api/v1/white-label surface is
 * OFF by default and only exists once the admin flips
 * `white_label_api.enabled`. When off we return a plain 404 so the API's very
 * existence isn't advertised to an unauthorized prober.
 */
class EnsureWhiteLabelApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) Setting::getValue('white_label_api.enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
