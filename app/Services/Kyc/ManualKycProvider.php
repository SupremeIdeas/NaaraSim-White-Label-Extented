<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use Illuminate\Http\Request;

/**
 * The always-available fallback: no external provider configured, so a submitted
 * verification waits in `pending` for a super-admin/admin to approve or reject
 * it by hand (ROADMAP §Layer 0.3 — "Admin sets the provider"; until they do, KYC
 * still works via manual review). No webhooks.
 */
class ManualKycProvider implements KycProviderInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function available(): bool
    {
        return true; // the fallback is always available
    }

    public function submit(KycVerification $verification, array $data): KycResult
    {
        // Keep only non-sensitive descriptors for the reviewer; never store the
        // raw ID number in checks.
        $checks = [
            'submitted' => true,
            'id_type' => $data['id_type'] ?? null,
            'country' => $data['country'] ?? null,
        ];

        return new KycResult(status: KycVerification::PENDING, checks: $checks);
    }

    public function verifyWebhook(Request $request): bool
    {
        return false; // manual review has no callback
    }

    public function parseWebhook(Request $request): ?KycEvent
    {
        return null;
    }
}
