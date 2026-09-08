<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * After login, return the user to where they were HEADING, not always the
 * dashboard. This is what makes the secret admin path usable from any device:
 * a guest who hits /adminmaster is bounced to /login with the intended URL
 * remembered (EnsureAdmin uses redirect()->guest()), and on success they land
 * back on /adminmaster instead of being dropped on the user dashboard. Everyone
 * else (no intended URL) still lands on the configured home page.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }
}
