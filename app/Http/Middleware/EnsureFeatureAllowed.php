<?php

namespace App\Http\Middleware;

use App\Support\FeatureEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature.allowed:gift_cards` — a page of a feature this install's licence does not include answers a plain 404, for members and admins alike, as if the
 * page did not exist. Registered as a Livewire persistent middleware too, so an action on a page opened before the lock arrived is refused as well.
 */
class EnsureFeatureAllowed
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        foreach ($keys as $key) {
            if (FeatureEntitlements::locked($key)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
