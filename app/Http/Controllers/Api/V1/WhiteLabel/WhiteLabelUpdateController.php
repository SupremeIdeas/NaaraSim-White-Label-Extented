<?php

namespace App\Http\Controllers\Api\V1\WhiteLabel;

use App\Http\Controllers\Controller;
use App\Jobs\AlertAdminJob;
use App\Models\WhiteLabelInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * White-label distribution API — code update packages (Batch 4 §3.1/§3.2).
 * Serves the `code`/`code_and_migrations`/`migrations` package family. All
 * check/download logic lives in DistributesPackages; this controller only names
 * the family it serves, plus the outcome-report endpoint below.
 */
class WhiteLabelUpdateController extends Controller
{
    use DistributesPackages;

    protected function family(): string
    {
        return 'code';
    }

    /**
     * Closes the oversight loop Batch 4 left open (Batch 5 §4): Batch 4 knows
     * what an instance DOWNLOADED, not what happened when it actually applied
     * the package. A subscriber's WhiteLabelUpdateClient::reportOutcome() posts
     * here after every apply attempt — success, rollback, or failure — mirroring
     * platform_update_attempts' own shape since that's exactly what's being
     * reported back.
     *
     * Reuses the `updates.check` scope (no new scope needed — reporting an
     * outcome is a natural extension of the checking relationship) and serves
     * BOTH code and theme outcomes (the package_id already identifies which).
     * The row itself is written by the whitelabel.usable middleware, which logs
     * every authenticated call centrally; this action only validates the
     * payload and raises the alert a failed/rolled-back update deserves.
     */
    /**
     * The fork's feature-entitlement (Batch 8): its current level + the resolved
     * lock list. The fork polls this on check-in and caches it locally, then
     * enforces the locks through FeatureEntitlements. Reuses the `updates.check`
     * scope — a fork allowed to check for updates is allowed to read what it's
     * entitled to; no new scope, no new gate.
     */
    public function entitlement(Request $request): JsonResponse
    {
        /** @var WhiteLabelInstance $instance */
        $instance = $request->user();

        return response()->json(\App\Support\FeatureLocks::entitlementFor($instance->entitlement_level));
    }

    public function report(Request $request): JsonResponse
    {
        /** @var WhiteLabelInstance $instance */
        $instance = $request->user();

        $validated = $request->validate([
            'package_id' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:applied,rolled_back,failed'],
            'downtime_seconds' => ['nullable', 'integer', 'min:0'],
            'applied_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($validated['status'], ['rolled_back', 'failed'], true)) {
            AlertAdminJob::dispatch(
                'white_label.update_'.$validated['status'],
                "White-label instance '{$instance->brand_name}' reported its update to package {$validated['package_id']} as {$validated['status']}.",
                ['instance_id' => $instance->id, 'brand' => $instance->brand_name, ...$validated],
                'critical',
            );
        }

        return response()->json(['message' => 'Recorded.']);
    }
}
