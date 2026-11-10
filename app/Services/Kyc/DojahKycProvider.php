<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            : $this->submitIndividual($verification, $data);
    }

    private function submitIndividual(KycVerification $verification, array $data): KycResult
    {
        [$response, $early] = $this->lookup('/api/v1/kyc/nin', ['nin' => $data['id_number'] ?? null], 'Identity not found.');
        if ($early !== null) {
            return $early;
        }

        $entity = (array) data_get($response, 'entity');
        // A real NIN is not proof it is THIS person's: the name on the record must share a name with the account, else a human decides.
        if (! $this->nameMatches((string) ($verification->user?->name ?? ''), $entity)) {
            return new KycResult(status: KycVerification::PENDING, checks: ['name_match' => false], reason: 'The name on the ID does not match the account name. Staff will review it.');
        }

        return new KycResult(status: KycVerification::APPROVED, checks: ['verified' => true, 'name_match' => true]);
    }

    /**
     * One classified Dojah GET. Returns [body, null] on success or [null, KycResult] when the call already decides the outcome:
     * 400/404/422 = no such record (rejected); 402 = OUR Dojah wallet is empty, never the customer's fault, so it goes to manual
     * review and the operator is alerted; 401/403 = our credentials are wrong (failed, logged); anything else = unreachable (failed).
     *
     * @param  array<string, mixed>  $query
     * @return array{0: array<string, mixed>|null, 1: KycResult|null}
     */
    private function lookup(string $path, array $query, string $notFound): array
    {
        try {
            $r = Http::acceptJson()->timeout(20)->withHeaders([
                'AppId' => (string) config('services.dojah.app_id'),
                'Authorization' => (string) config('services.dojah.api_key'),
            ])->get(rtrim((string) config('services.dojah.base_url'), '/').$path, $query);
        } catch (\Throwable) {
            return [null, new KycResult(status: KycVerification::FAILED, reason: 'Could not reach Dojah.')];
        }

        if ($r->successful()) {
            $body = (array) $r->json();

            return data_get($body, 'entity') === null ? [null, new KycResult(status: KycVerification::REJECTED, reason: $notFound)] : [$body, null];
        }
        if ($r->status() === 402) {
            Log::critical('Dojah wallet is empty: KYC lookups are going to manual review until it is topped up.');

            return [null, new KycResult(status: KycVerification::PENDING, reason: 'Awaiting manual review.')];
        }
        if (in_array($r->status(), [400, 404, 422], true)) {
            return [null, new KycResult(status: KycVerification::REJECTED, reason: $notFound)];
        }
        if (in_array($r->status(), [401, 403], true)) {
            Log::error('Dojah refused our credentials (HTTP '.$r->status().'): check DOJAH_APP_ID and the secret key.');
        }

        return [null, new KycResult(status: KycVerification::FAILED, reason: 'Could not reach Dojah.')];
    }

    /** @param array<string, mixed> $entity */
    private function nameMatches(string $accountName, array $entity): bool
    {
        $norm = fn (string $v): array => array_values(array_filter(preg_split('/[^a-z]+/', strtolower(Str::ascii($v))) ?: [], fn ($t) => strlen($t) > 1));
        $record = $norm(implode(' ', array_filter([(string) ($entity['first_name'] ?? ''), (string) ($entity['middle_name'] ?? ''), (string) ($entity['last_name'] ?? $entity['surname'] ?? '')])));
        $account = $norm($accountName);
        if ($record === [] || $account === []) {
            return true;   // nothing to compare against: the ID check alone stands
        }

        return array_intersect($account, $record) !== [];
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

        [$response, $early] = $this->lookup('/api/v1/kyc/cac', ['rc_number' => $rcNumber], 'Business registration not found.');
        if ($early !== null) {
            return $early;
        }
        $entity = data_get($response, 'entity');

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
