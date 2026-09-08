<?php

namespace App\Http\Controllers\Api\V1\WhiteLabel;

use App\Exceptions\LicenseActivationException;
use App\Http\Controllers\Controller;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\WhiteLabelLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * License Authority endpoints (Batch 6) — the pre-auth enrolment surface, sitting
 * under the white-label API feature flag but OUTSIDE auth:sanctum/whitelabel.usable
 * (a fresh fork has no token yet, so these can't be token-gated). Two actions:
 *
 *   - register: a self-serve request to become a subscriber. Creates a pending
 *     instance an admin then reviews and issues a license for. Deliberately gives
 *     back nothing but an acknowledgement — no key, no token — so it can't be used
 *     to fish for anything.
 *   - activate: the deployed fork exchanges its admin-issued license key for a
 *     Sanctum API token (what NAARA_UPDATE_API_TOKEN becomes on the fork). Every
 *     unusable-key path returns the same generic 403 so a prober can't tell an
 *     unknown key from a revoked one.
 *
 * Both are rate-limited at the route (throttle:api) — activate especially, since
 * it's the one place a valid key mints access.
 */
class WhiteLabelLicenseController extends Controller
{
    public function register(Request $request, WhiteLabelLicenseService $licenses): JsonResponse
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:white_label_instances,slug'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $instance = $licenses->register($validated);

        // Acknowledge only. Never expose status, key, or token from this endpoint.
        return response()->json([
            'message' => 'Registration received. A license will be issued after review.',
            'reference' => $instance->slug,
        ], 202);
    }

    public function activate(Request $request, WhiteLabelLicenseService $licenses): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:64'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'current_version' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $result = $licenses->activateWithKey($validated['license_key'], [
                'brand_name' => $validated['brand_name'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'current_platform_version' => $validated['current_version'] ?? null,
            ]);
        } catch (LicenseActivationException $e) {
            // The real reason is logged for oversight; the caller only ever sees
            // the generic message so unknown/revoked/suspended are indistinguishable.
            Log::warning('white_label.activation_denied', ['reason' => $e->reason, 'ip' => $request->ip()]);

            return response()->json(['message' => $e->getMessage()], 403);
        }

        /** @var WhiteLabelInstance $instance */
        $instance = $result['instance'];

        return response()->json([
            'message' => 'License activated.',
            'token' => $result['token'],
            'instance' => [
                'brand_name' => $instance->brand_name,
                'slug' => $instance->slug,
                'tier' => $instance->tier,
                'status' => $instance->status,
            ],
        ]);
    }
}
