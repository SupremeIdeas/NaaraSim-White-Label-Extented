<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Dojah (ROADMAP §Layer 0.3) — BVN/NIN/document/biometric, fast synchronous
 * lookups. For an L2 ID check Dojah returns a match immediately, so submit()
 * yields a synchronous approved/rejected. Coded to the documented API shape and
 * key-gated; the real HTTP can only run with live keys.
 */
class DojahKycProvider implements KycProviderInterface
{
    public function name(): string
    {
        return 'dojah';
    }

    public function available(): bool
    {
        return filled(config('services.dojah.app_id')) && filled(config('services.dojah.api_key'));
    }

    public function submit(KycVerification $verification, array $data): KycResult
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'AppId' => (string) config('services.dojah.app_id'),
                    'Authorization' => (string) config('services.dojah.api_key'),
                ])
                ->get(rtrim((string) config('services.dojah.base_url'), '/').'/api/v1/kyc/nin', [
                    'nin' => $data['id_number'] ?? null,
                ])->throw()->json();
        } catch (\Throwable $e) {
            return new KycResult(status: KycVerification::FAILED, reason: 'Could not reach Dojah.');
        }

        $entity = data_get($response, 'entity');
        if ($entity === null) {
            return new KycResult(status: KycVerification::REJECTED, reason: 'Identity not found.');
        }

        return new KycResult(status: KycVerification::APPROVED, checks: ['verified' => true]);
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header('x-dojah-signature');
        $secret = (string) config('services.dojah.api_key');

        // Pentest finding (2026-09-16): an empty API key must never validate —
        // hash_hmac(..., '') is computable by anyone, so without this guard an
        // unconfigured Dojah could have a forged webhook approve a user's own
        // KYC verification without ever submitting a real document.
        if (! is_string($signature) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?KycEvent
    {
        $ref = (string) $request->input('reference_id', '');
        if ($ref === '') {
            return null;
        }

        $status = strtolower((string) $request->input('status', ''));

        return new KycEvent(
            provider: $this->name(),
            reference: $ref,
            status: $status === 'completed' || $status === 'approved' ? KycVerification::APPROVED : KycVerification::REJECTED,
            reason: $status === 'completed' || $status === 'approved' ? null : 'Not verified.',
        );
    }
}
