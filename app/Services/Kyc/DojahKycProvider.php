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
 *
 * Tier 5 #11 Phase C — also handles L3 (KYB): Dojah's CAC product looks up a
 * Nigerian business by its CAC/RC number against the Corporate Affairs
 * Commission registry. `KycService::resolveProvider()` only ever routes an L3
 * submission here for a Nigerian (`NG`) applicant using the `CAC` registration
 * type — every other country/type combination goes straight to manual review,
 * since this is the one business-registry lookup actually confirmed to exist
 * on Dojah's documented API; nothing here pretends to cover the other 190+
 * countries in the business-registration catalogue.
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
        return $verification->level === KycVerification::L3
            ? $this->submitBusiness($data)
            : $this->submitIndividual($data);
    }

    private function submitIndividual(array $data): KycResult
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

    /**
     * Only a `CAC` registration number is actually verifiable here today. A
     * Nigerian applicant registered under a different type (e.g. TIN) stays
     * `pending` for manual admin review, rather than being wrongly rejected
     * for a lookup this endpoint was never going to be able to answer.
     */
    private function submitBusiness(array $data): KycResult
    {
        if (strtoupper((string) ($data['id_type'] ?? '')) !== 'CAC') {
            return new KycResult(status: KycVerification::PENDING, reason: 'Awaiting manual review.');
        }

        $rcNumber = $data['id_number'] ?? null;
        if (blank($rcNumber)) {
            return new KycResult(status: KycVerification::FAILED, reason: 'Missing CAC/RC number.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'AppId' => (string) config('services.dojah.app_id'),
                    'Authorization' => (string) config('services.dojah.api_key'),
                ])
                ->get(rtrim((string) config('services.dojah.base_url'), '/').'/api/v1/kyc/cac', [
                    'rc_number' => $rcNumber,
                ])->throw()->json();
        } catch (\Throwable $e) {
            return new KycResult(status: KycVerification::FAILED, reason: 'Could not reach Dojah.');
        }

        $entity = data_get($response, 'entity');
        if ($entity === null) {
            return new KycResult(status: KycVerification::REJECTED, reason: 'Business registration not found.');
        }

        return new KycResult(status: KycVerification::APPROVED, checks: [
            'verified' => true,
            'company_name' => data_get($entity, 'company_name'),
        ]);
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
