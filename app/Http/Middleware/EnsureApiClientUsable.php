<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirms the authenticated Sanctum entity is a usable Developer API client
 * (ROADMAP §Layer 2): it must be an ApiClient, active, and owned by a still-active
 * developer. Suspending the client OR its owner instantly blocks every request.
 * Also stamps last_used_at for usage analytics.
 */
class EnsureApiClientUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->user();

        if (! $client instanceof ApiClient || ! $client->usable()) {
            abort(403, 'This API client is not permitted.');
        }

        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
