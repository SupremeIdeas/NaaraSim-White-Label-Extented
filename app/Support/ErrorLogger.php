<?php

namespace App\Support;

use App\Models\ErrorLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Durable, exportable error capture (blueprint Section 17.5). Writes server
 * errors to the error_logs table (which the admin ErrorLog module lists +
 * exports). Sentry captures the same exceptions independently when a DSN is
 * configured. Expected HTTP/validation/auth exceptions are skipped as noise.
 */
class ErrorLogger
{
    public static function capture(Throwable $e): void
    {
        if ($e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof HttpExceptionInterface) {
            return;
        }

        try {
            ErrorLog::create([
                'code' => class_basename($e),
                'message' => $e->getMessage() ?: class_basename($e),
                'context' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'exception' => $e::class,
                ],
                'severity' => 'error',
            ]);
        } catch (Throwable $inner) {
            // Never let error logging throw (e.g. DB unavailable) — that would
            // mask the original failure.
        }
    }
}
