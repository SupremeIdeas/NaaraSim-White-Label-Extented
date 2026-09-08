<?php

namespace App\Http\Responses;

use App\Support\WelcomeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * After a successful signup, route the brand-new user through the Aurora Welcome
 * entrance (first-login animation) before the dashboard. A `just_registered`
 * session flag is what the /welcome screen keys on; when the animation is turned
 * off in admin we skip straight to the normal home so nothing feels broken.
 */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        if (WelcomeSettings::enabled()) {
            $request->session()->flash('just_registered', true);

            return redirect()->route('welcome');
        }

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }
}
