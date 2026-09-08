<?php

namespace App\Http\Middleware;

use App\Models\WhiteLabelInstance;
use App\Services\Updater\WhiteLabelActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Confirms the authenticated Sanctum entity is a usable white-label instance
 * (Batch 4 §3), mirroring EnsureApiClientUsable — it must be a WhiteLabelInstance
 * with status = active. Stamps last_checked_in_at (the white-label equivalent of
 * ApiClient's last_used_at).
 *
 * New relative to the Developer API pattern: it also records exactly one
 * white_label_api_logs row per authenticated call, AFTER the response is known,
 * so the logged status reflects the true final outcome — including a scope
 * denial (403) from the api.scope middleware that runs after this one, and the
 * controller's own status. Central logging in one place means no controller can
 * forget to log.
 */
class EnsureWhiteLabelInstanceUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $instance = $request->user();

        if (! $instance instanceof WhiteLabelInstance || ! $instance->usable()) {
            abort(403, 'This white-label instance is not permitted.');
        }

        $instance->forceFill(['last_checked_in_at' => now()])->saveQuietly();

        // Log EXACTLY ONCE per authenticated call, with the true final status —
        // including a scope denial (403) or validation failure (422) that
        // surfaces as a thrown exception downstream (which would otherwise
        // unwind past a naive post-$next log). We record then rethrow so the
        // request's own behaviour is completely unchanged.
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->log($instance, $request, $this->statusFor($e));
            throw $e;
        }

        $this->log($instance, $request, $response->getStatusCode());

        return $response;
    }

    private function statusFor(\Throwable $e): int
    {
        return match (true) {
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            $e instanceof ValidationException => $e->status,
            default => 500,
        };
    }

    private function log(WhiteLabelInstance $instance, Request $request, int $status): void
    {
        WhiteLabelActivityLogger::record(
            instance: $instance,
            endpoint: $request->route()?->getName() ?? $request->path(),
            method: $request->method(),
            status: $status,
            ip: $request->ip(),
            context: array_filter([
                'current_version' => $request->query('current_version'),
                'product' => $request->query('product'),
                'package' => $request->route('package'),
            ], fn ($v) => $v !== null),
        );
    }
}
