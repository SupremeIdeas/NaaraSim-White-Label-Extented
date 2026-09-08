<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Readiness-audit fix (2026-09-07): Fortify's own route file only attaches
 * `throttle:` middleware to login/two-factor/passkeys — `POST /register` and
 * `POST /forgot-password` ship with NO rate limit at all, in this Fortify
 * version or the last several. That leaves account-creation floodable and
 * the password-reset-email endpoint open to inbox-bombing/email-enumeration
 * at unlimited volume. Rather than fight Fortify's route registration (which
 * would mean re-declaring every auth route, login included, to keep the
 * ones that already work), this self-gates on path exactly like
 * VerifyTurnstile does, and applies Laravel's own RateLimiter directly.
 *
 * Fails safe in the RateLimiter's own default direction: on any driver
 * error the increment simply doesn't happen — this never blocks a
 * legitimate request due to an infra hiccup, matching Turnstile's
 * fail-open-when-unavailable posture used right next to it.
 */
class ThrottleUnprotectedAuthRoutes
{
    /** 5 registrations per IP per hour; 5 reset-emails per IP+email per hour. */
    private const REGISTER_MAX = 5;

    private const RESET_MAX = 5;

    private const DECAY_SECONDS = 3600;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        if ($request->is('register')) {
            return $this->throttle($request, 'register:'.$request->ip(), self::REGISTER_MAX, $next);
        }

        if ($request->is('forgot-password')) {
            $email = mb_strtolower(trim((string) $request->input('email', '')));
            $key = 'password-reset:'.$email.'|'.$request->ip();

            return $this->throttle($request, $key, self::RESET_MAX, $next);
        }

        return $next($request);
    }

    private function throttle(Request $request, string $key, int $max, Closure $next): Response
    {
        if ($this->limiter->tooManyAttempts($key, $max)) {
            $seconds = $this->limiter->availableIn($key);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => "Too many attempts. Please try again in {$seconds} seconds."]);
        }

        $this->limiter->hit($key, self::DECAY_SECONDS);

        return $next($request);
    }
}
