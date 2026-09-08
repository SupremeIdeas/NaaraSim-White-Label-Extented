<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum SPA statefulness for first-party requests.
        $middleware->statefulApi();

        // Trust the proxy chain (NAARA-BUILD-20 §1). On shared cPanel the SSL is
        // terminated by a front proxy that hands Laravel a plain-HTTP internal
        // request while the real browser connection was HTTPS. Without this,
        // Laravel builds signed URLs (email verification, password reset) with the
        // wrong scheme and every click 403s on signature validation. The proxy IP
        // is not fixed on shared hosting, so trust all and read X-Forwarded-*.
        $middleware->trustProxies(at: '*');

        // Fresh upload with no lock file -> web installer (blueprint S22.1).
        $middleware->web(append: [
            \App\Http\Middleware\RedirectIfNotInstalled::class,
        ]);

        // Provider webhooks carry no CSRF token; verification is per-provider
        // (HMAC/shared-secret) inside each handler.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'active' => \App\Http\Middleware\EnsureActive::class,
            'verified.mail' => \App\Http\Middleware\EnsureVerifiedWhenMailConfigured::class,
            'installer' => \App\Http\Middleware\EnsureNotInstalled::class,
            // Staff role/scope gating (blueprint Section 27).
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            // Developer API (ROADMAP §Layer 2): feature flag, client-usability
            // gate, and per-scope (Sanctum ability) enforcement.
            'api.enabled' => \App\Http\Middleware\EnsureDeveloperApiEnabled::class,
            'api.client' => \App\Http\Middleware\EnsureApiClientUsable::class,
            'api.scope' => \App\Http\Middleware\ApiScope::class,
            // KYC level gate (ROADMAP §Layer 0.3): kyc:2 to withdraw, kyc:3 to
            // become a merchant.
            'kyc' => \App\Http\Middleware\EnsureKycLevel::class,
        ]);

        // Security headers on every web response (blueprint Section 19.2; the
        // full CSP matrix is Module 19).
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            // Turnstile bot check on the auth POSTs (self-gates; no-op unless
            // active — blueprint Section 33).
            \App\Http\Middleware\VerifyTurnstile::class,
            // Fortify ships POST /register and POST /forgot-password with NO
            // rate limit at all (readiness-audit fix, 2026-09-07) — self-gates
            // on path exactly like VerifyTurnstile above.
            \App\Http\Middleware\ThrottleUnprotectedAuthRoutes::class,
            // Inbound webhook delivery log (readiness Domain 13/14) — observability
            // only, self-scopes to webhooks/* and never alters handler behaviour.
            \App\Http\Middleware\LogWebhookDelivery::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Durable, exportable error capture (Section 17.5).
        $exceptions->report(function (\Throwable $e) {
            \App\Support\ErrorLogger::capture($e);
        });

        // The Developer API (ROADMAP §Layer 2) is JSON-only: every error on an
        // /api/* route renders as JSON — including an unauthenticated request —
        // so a client is never redirected to an HTML login page (a 401 they can
        // act on, not a confusing 302). Applies regardless of the Accept header.
        $exceptions->shouldRenderJsonWhen(
            fn (\Illuminate\Http\Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never show users the raw "419 Page Expired". A stale CSRF token (an
        // old tab, the back button, a slow connection) surfaces as a 419
        // HttpException; we auto-recover by sending them back to the form they
        // submitted — which re-renders with a fresh token — plus a gentle "please
        // try again" message, so a resubmit just works. APIs get a clean JSON
        // 419; any other HTTP error falls through to normal rendering.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session expired. Please refresh and try again.'], 419);
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('status', 'Your session timed out for security — please try again.');
        });
    })->create();
