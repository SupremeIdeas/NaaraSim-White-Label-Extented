<?php

namespace App\Http\Middleware;

use App\Support\AdminPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * NAARA-PROMPT-22 §2.4 — resolves the wildcard {adminGateway} route segment
 * against the current, database-backed admin path. Runs first in every admin
 * route's middleware stack (guest login/recover included), before anything
 * else — IP allow-list, auth, 2FA — so a wrong path 404s exactly like the
 * existing IP allow-list behaviour: whoever's probing learns nothing, not
 * even that an admin panel exists at a different path.
 */
class EnsureAdminGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $gateway = (string) $request->route('adminGateway');

        if (! hash_equals(AdminPath::current(), $gateway)) {
            throw new NotFoundHttpException;
        }

        // Drop it from the route's own parameter bag once validated. Laravel's
        // controller dispatcher falls back to positional binding when a route
        // parameter isn't already an object matching the target type-hint —
        // left in place, this extra leading string parameter would shift into
        // a controller's first argument on any admin route that ALSO binds a
        // model (e.g. {intake}), silently handing it a path string instead.
        $request->route()->forgetParameter('adminGateway');

        return $next($request);
    }
}
