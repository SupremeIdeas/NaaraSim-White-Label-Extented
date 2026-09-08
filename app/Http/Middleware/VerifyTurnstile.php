<?php

namespace App\Http\Middleware;

use App\Support\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify the Cloudflare Turnstile token (blueprint Section 33) on the guarded
 * POST endpoints (login, register). Fails OPEN only when Turnstile is not
 * active — if the admin has not enabled + configured it, auth is untouched.
 * When active, a missing or invalid token is rejected before the request ever
 * reaches Fortify, so bots never get to attempt a credential check.
 */
class VerifyTurnstile
{
    /** Guarded endpoints: the classic auth form POSTs (Fortify owns the routes). */
    private function guards(Request $request): bool
    {
        return $request->isMethod('POST') && ($request->is('login') || $request->is('register'));
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->guards($request) || ! Turnstile::active()) {
            return $next($request);
        }

        $token = (string) $request->input('cf-turnstile-response', '');

        if ($token === '' || ! $this->verify($token, $request->ip())) {
            return back()
                ->withInput($request->except('password', 'password_confirmation', 'cf-turnstile-response'))
                ->withErrors(['email' => 'Please complete the “I’m human” check and try again.']);
        }

        return $next($request);
    }

    private function verify(string $token, ?string $ip): bool
    {
        try {
            $response = Http::asForm()->timeout(10)->post(config('services.turnstile.verify_url'), [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return $response->ok() && ($response->json('success') === true);
        } catch (\Throwable) {
            // A Cloudflare outage must not lock everyone out — degrade to allow,
            // exactly like a disabled challenge. (Bots still face rate limits.)
            return true;
        }
    }
}
