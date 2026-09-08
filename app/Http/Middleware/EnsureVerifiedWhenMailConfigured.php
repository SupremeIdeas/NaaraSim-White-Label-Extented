<?php

namespace App\Http\Middleware;

use App\Support\MailSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conditional email-verification gate (owner request). Email confirmation is
 * only enforced once the operator has actually configured outgoing mail — before
 * that, forcing "verify your email" would trap every user behind a link that can
 * never arrive. So:
 *   - Mail NOT configured  -> users sign in with email + password and use the app.
 *   - Mail configured      -> unverified users are held at the verification notice
 *                             until they confirm (mandatory before the app opens).
 *
 * Applied to the money/core routes; /account stays reachable either way so a user
 * can always manage their account and resend the link.
 */
class EnsureVerifiedWhenMailConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only the 'hard' mode ever blocks (NAARA-BUILD-20 §2). 'off' and 'soft'
        // let unverified users browse, buy, and use every feature; 'soft' nudges
        // with a dismissible banner instead of a wall. Mail-not-configured is
        // treated like 'off' — nothing to verify against yet.
        if (! MailSettings::isConfigured() || MailSettings::verificationMode() !== MailSettings::MODE_HARD) {
            return $next($request);
        }

        $user = $request->user();
        if ($user !== null
            && $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
            && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
