<?php

namespace App\Support;

use App\Models\AuditLog;

/**
 * Immutable audit trail for admin/staff actions and pricing changes (blueprint
 * Sections 18.2 & 19). Records who did what, to which model, from where.
 * Every admin mutation should go through here.
 */
class Auditor
{
    public static function log(string $action, ?string $model = null, ?int $modelId = null, array $payload = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'payload' => $payload,
        ]);
    }
}
