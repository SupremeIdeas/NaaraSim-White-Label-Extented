<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use Illuminate\Http\Request;

/**
 * Contract for a KYC/KYB provider (ROADMAP §Layer 0.3), resolved by name via
 * app("kyc.$provider"). Pluggable so the admin can choose Smile ID (default,
 * pan-African BVN/NIN + liveness), Dojah, or a manual admin-review fallback.
 * Mirrors the payout gateway seam: key-gated availability, and a webhook path
 * whose signature is verified BEFORE the payload is trusted.
 */
interface KycProviderInterface
{
    public function name(): string;

    /** True once configured (keys present, or always for manual review). */
    public function available(): bool;

    /**
     * Submit a verification. `$data` carries the level-appropriate inputs (an ID
     * number for L2, business docs for L3). Returns the immediate result — a
     * synchronous decision, a `pending` awaiting webhook, or a hosted redirect.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(KycVerification $verification, array $data): KycResult;

    /** Verify a webhook signature (constant-time) before trusting it. */
    public function verifyWebhook(Request $request): bool;

    /** Parse a (verified) webhook into a normalised event. */
    public function parseWebhook(Request $request): ?KycEvent;
}
