<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every inbound webhook delivery (Laravel readiness — Domain 13/14). Runs
 * only on webhooks/* and only AFTER the handler has produced its response, so it
 * can never change a handler's behaviour. Wrapped in try/catch — logging a
 * delivery must never break receiving one.
 */
class LogWebhookDelivery
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('webhooks/*')) {
            try {
                // provider = the path segment after "webhooks/".
                $segments = explode('/', trim($request->path(), '/'));
                $provider = $segments[1] ?? 'unknown';

                DB::table('webhook_deliveries')->insert([
                    'provider' => mb_substr($provider, 0, 40),
                    'path' => mb_substr($request->path(), 0, 191),
                    'status_code' => $response->getStatusCode(),
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // Never let observability break webhook receipt.
            }
        }

        return $response;
    }
}
