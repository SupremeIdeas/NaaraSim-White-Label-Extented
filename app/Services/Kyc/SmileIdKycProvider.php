<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Smile ID (ROADMAP §Layer 0.3) — broadest pan-African coverage (BVN/NIN,
 * document + liveness). Smile ID runs verification as a job confirmed via a
 * callback, so submit() records the job and returns `pending`; the signed
 * callback (verified before trust) delivers the final decision. Coded to the
 * documented API shape and key-gated; the real HTTP can only run with live keys.
 */
class SmileIdKycProvider implements KycProviderInterface
{
    public function name(): string
    {
        return 'smileid';
    }

    public function available(): bool
    {
        return filled(config('services.smileid.partner_id')) && filled(config('services.smileid.api_key'));
    }

    public function submit(KycVerification $verification, array $data): KycResult
    {
        try {
            Http::acceptJson()
                ->withHeaders(['partner_id' => (string) config('services.smileid.partner_id')])
                ->post(rtrim((string) config('services.smileid.base_url'), '/').'/v2/verify', [
                    'partner_params' => ['job_id' => $verification->reference, 'user_id' => (string) $verification->user_id],
                    'country' => $data['country'] ?? null,
                    'id_type' => $data['id_type'] ?? null,
                    'id_number' => $data['id_number'] ?? null,
                    'callback_url' => route('webhooks.kyc', 'smileid'),
                ])->throw();
        } catch (\Throwable $e) {
            return new KycResult(status: KycVerification::FAILED, reason: 'Could not reach Smile ID.');
        }

        // The decision arrives on the signed callback.
        return new KycResult(status: KycVerification::PENDING, checks: ['job_id' => $verification->reference]);
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header('x-smileid-signature');
        $secret = (string) config('services.smileid.api_key');

        // Pentest finding (2026-09-16): an empty API key must never validate —
        // hash_hmac(..., '') is computable by anyone, so without this guard an
        // unconfigured Smile ID could have a forged webhook approve a user's
        // own KYC verification without ever submitting a real document.
        if (! is_string($signature) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?KycEvent
    {
        $ref = (string) $request->input('partner_params.job_id', $request->input('job_id', ''));
        if ($ref === '') {
            return null;
        }

        // Smile ID result codes: "1012"/"0810" (approved) etc.; treat an explicit
        // approving ResultCode as pass, everything else as a rejection.
        $code = (string) $request->input('ResultCode', '');
        $approved = in_array($code, ['1012', '0810', '1020'], true);

        return new KycEvent(
            provider: $this->name(),
            reference: $ref,
            status: $approved ? KycVerification::APPROVED : KycVerification::REJECTED,
            checks: ['result_code' => $code],
            reason: $approved ? null : (string) $request->input('ResultText', 'Not verified.'),
        );
    }
}
