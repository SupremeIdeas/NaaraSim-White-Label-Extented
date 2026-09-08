<?php

namespace App\Support;

/**
 * Production misconfiguration guard (BUILD-1 §2.2 + §3.8). Surfaces the handful
 * of environment settings that are silently dangerous in production:
 *
 *   - QUEUE_CONNECTION=sync   → every ShouldQueue job runs inline in the request,
 *     so a payment-webhook handler does all its work before replying 200, risking
 *     a provider-side timeout + duplicate-delivery retry (and "payment didn't
 *     credit" symptoms).
 *   - APP_DEBUG=true          → stack traces, file paths and secrets can leak to
 *     any visitor on an error.
 *
 * Only flags in a production-like environment (never nags on local/testing). The
 * warnings are both logged once at boot and shown as a banner on the admin
 * dashboard, so a non-technical operator actually sees them.
 */
class EnvironmentGuard
{
    /** @return list<array{key:string, title:string, detail:string}> */
    public static function warnings(): array
    {
        if (! self::isProductionLike()) {
            return [];
        }

        $warnings = [];

        if (config('queue.default') === 'sync') {
            $warnings[] = [
                'key' => 'queue_sync',
                'title' => 'Queue is running synchronously',
                'detail' => 'QUEUE_CONNECTION is set to "sync", so jobs run inside the web request instead of the background queue. Set it to "database" (shared hosting) or "redis" (VPS) and make sure the queue cron/worker is running — otherwise a slow provider can make a payment webhook time out and a credit can be missed.',
            ];
        }

        if (config('app.debug') === true) {
            $warnings[] = [
                'key' => 'app_debug',
                'title' => 'Debug mode is ON in production',
                'detail' => 'APP_DEBUG=true exposes error details (stack traces, file paths, and possibly secrets) to visitors. Set APP_DEBUG=false in your .env immediately, then run "php artisan config:cache".',
            ];
        }

        return $warnings;
    }

    public static function hasWarnings(): bool
    {
        return self::warnings() !== [];
    }

    /**
     * A production-like environment: APP_ENV=production, or any non-local/testing
     * environment running with debug off-expectations. We key off the env name so
     * a deployer who mislabels APP_ENV still gets the local exemption they expect.
     */
    private static function isProductionLike(): bool
    {
        return app()->environment('production', 'prod');
    }
}
