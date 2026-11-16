<?php

namespace App\Http\Middleware;

use App\Support\AppLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Server-side app lock. A locked session sees only the lock screen (pages) or a 423 (JSON / Livewire) until it is unlocked. */
class EnforceAppLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! AppLock::enabled($user) || ! $request->hasSession()) {
            return $next($request);
        }
        $name = $request->route()?->getName() ?? '';
        $exempt = str_starts_with($name, 'app-lock.') || in_array($name, ['logout', 'passkey.confirm', 'passkey.confirm-options'], true) || $request->is('up');

        if (AppLock::isLocked($request->session(), $user)) {
            if ($exempt) {
                return $next($request);
            }
            if ($request->expectsJson() || $request->headers->has('X-Livewire')) {
                return response()->json(['message' => 'Locked'], 423);
            }

            if ($request->isMethod('GET')) {
                $request->session()->put('app_lock_back', $request->fullUrl());
            }

            return response()->view('auth.app-lock', ['user' => $user, 'hasPasskey' => $user->passkeys()->exists()], 423)->header('Cache-Control', 'no-store');
        }

        // Real use refreshes the idle clock (background polls do not).
        if (! $request->headers->has('X-Nx-Poll')) {
            AppLock::touch($request->session());
        }

        return $next($request);
    }
}
