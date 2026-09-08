<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confines a self-paused account (blueprint Section 26.1) to the account page,
 * where it can reactivate. A deactivated user can still log in — but every
 * other customer route redirects to /account until they resume. Admins are
 * exempt (their own gate is EnsureAdmin).
 */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->isDeactivated()
            && ! $request->routeIs('account')
            && ! $request->routeIs('account.export.download')
            && ! $request->routeIs('logout')) {
            return redirect()->route('account')
                ->with('paused', 'Your account is paused. Reactivate it to keep using NaaraSim.');
        }

        return $next($request);
    }
}
