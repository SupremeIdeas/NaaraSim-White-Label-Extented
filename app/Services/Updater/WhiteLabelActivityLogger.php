<?php

namespace App\Services\Updater;

use App\Models\WhiteLabelApiLog;
use App\Models\WhiteLabelInstance;

/**
 * Writes one append-only white_label_api_logs row per distribution-API call
 * (Batch 4 §2). Called from one place — the EnsureWhiteLabelInstanceUsable
 * middleware, after the response is known — so no controller can log
 * inconsistently or forget to log at all. Best-effort: a logging failure must
 * never turn a successful download into an error.
 *
 * @see \App\Http\Middleware\EnsureWhiteLabelInstanceUsable
 */
class WhiteLabelActivityLogger
{
    /**
     * @param  array<string,mixed>  $context
     */
    public static function record(
        WhiteLabelInstance $instance,
        string $endpoint,
        string $method,
        int $status,
        ?string $ip = null,
        array $context = [],
    ): void {
        try {
            WhiteLabelApiLog::create([
                'white_label_instance_id' => $instance->id,
                'endpoint' => $endpoint,
                'method' => $method,
                'response_status' => $status,
                'ip_address' => $ip,
                'context' => $context === [] ? null : $context,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never let oversight logging break the request it is observing.
        }
    }
}
