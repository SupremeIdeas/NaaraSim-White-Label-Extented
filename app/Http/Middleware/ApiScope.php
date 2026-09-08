<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces that the Developer API token carries the required scope/ability
 * (ROADMAP §Layer 2). Scopes are the Sanctum token abilities set when the key was
 * issued, so a `catalogue`-only key can never reach `order`. Usage:
 * `->middleware('api.scope:order')`.
 */
class ApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        if (! $request->user()?->tokenCan($scope)) {
            abort(403, "This API key is missing the required scope: {$scope}.");
        }

        return $next($request);
    }
}
