<?php

namespace App\Services\Updater;

use App\Exceptions\LicenseActivationException;
use App\Models\WhiteLabelInstance;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * License Authority for the white-label program (Batch 6). The single place that
 * issues, exchanges, and revokes the two credentials a subscriber holds — kept
 * structurally parallel to ApiClientService (the Developer API's token authority)
 * so the token discipline is identical: the plaintext Sanctum token is returned
 * exactly ONCE at mint/rotation and never stored (only its last four, for
 * display), and any state change that should cut access deletes every live token.
 *
 * The credential split is the whole design:
 *   - the LICENSE KEY is the durable enrolment credential (issued by an admin,
 *     tied to a tier). It is not a bearer token; it only ever buys ONE thing —
 *     the right to mint an API token via activateWithKey().
 *   - the SANCTUM TOKEN is the operational bearer credential the fork calls the
 *     distribution API with. Rotatable and revocable independently of the key.
 *
 * Because a valid key mints access, activation is the security-sensitive seam:
 * it must reject a revoked key and any non-active instance, and must not reveal
 * which failure occurred (LicenseActivationException handles that).
 */
class WhiteLabelLicenseService
{
    /**
     * Self-serve registration REQUEST: records a would-be subscriber as a pending
     * instance with no key and no token. An admin reviews it and issues a license
     * (issueLicense) — mirrors Merchant's pending → active review trail. Idempotent
     * on slug is NOT assumed here; the caller validates uniqueness first.
     */
    public function register(array $details): WhiteLabelInstance
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => $this->cleanName($details['brand_name'] ?? 'White-label brand'),
            'slug' => $details['slug'] ?? Str::slug(($details['brand_name'] ?? 'brand').'-'.Str::random(6)),
            'contact_email' => $details['contact_email'] ?? '',
            'owner_user_id' => $details['owner_user_id'] ?? null,
            'registration_note' => isset($details['note']) ? Str::limit((string) $details['note'], 2000, '') : null,
            'status' => WhiteLabelInstance::PENDING,
        ]);

        Auditor::log('white_label.registered', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'slug' => $instance->slug,
        ]);

        return $instance;
    }

    /**
     * Issue (or re-issue) a license on an instance: generate a fresh unique key,
     * set the tier, mark it active, and stamp the review trail. This is what an
     * admin does after a purchase/approval. Re-issuing generates a NEW key and
     * kills any token minted from the old one, so a leaked key can be replaced
     * without deleting the instance's history.
     *
     * Note: issuing a license does NOT mint an API token — the deployed fork does
     * that itself by exchanging the key at activateWithKey(). The key is what you
     * hand the buyer.
     */
    public function issueLicense(WhiteLabelInstance $instance, string $tier, ?int $reviewerId = null): WhiteLabelInstance
    {
        $tier = $this->normalizeTier($tier);

        // A brand-new key invalidates anything minted from a prior one.
        $instance->tokens()->delete();

        $instance->forceFill([
            'tier' => $tier,
            'license_key' => $this->generateUniqueKey(),
            'api_token_last_four' => null,
            'license_issued_at' => now(),
            'license_revoked_at' => null,
            'status' => WhiteLabelInstance::ACTIVE,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.license_issued', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'tier' => $tier,
        ]);

        return $instance;
    }

    /**
     * Exchange a license key for a fresh API token (the deployed fork's activate
     * call). Validates the key against a live license on an ACTIVE instance, then
     * mints a token carrying the full scope set, recording only its last four.
     * The plaintext token is returned once.
     *
     * @param  array{brand_name?:string,contact_email?:string,current_platform_version?:string}  $forkDetails
     * @return array{instance: WhiteLabelInstance, token: string}
     *
     * @throws LicenseActivationException on any unusable-key condition (generic
     *   message, specific reason logged — never leak which condition to a prober).
     */
    public function activateWithKey(string $licenseKey, array $forkDetails = []): array
    {
        $key = trim($licenseKey);

        // Constant-work lookup: fetch by key, then check state — the generic
        // exception makes "no such key" and "revoked key" indistinguishable outside.
        $instance = $key === '' ? null : WhiteLabelInstance::where('license_key', $key)->first();

        if ($instance === null) {
            throw new LicenseActivationException('unknown_key');
        }
        if ($instance->license_revoked_at !== null) {
            throw new LicenseActivationException('revoked');
        }
        if (! $instance->usable()) {
            throw new LicenseActivationException('inactive_status:'.$instance->status);
        }

        return DB::transaction(function () use ($instance, $forkDetails) {
            // Record the details the fork self-reports at activation (its brand may
            // have been a placeholder when the admin issued the key ahead of sale).
            $update = array_filter([
                'brand_name' => isset($forkDetails['brand_name']) ? $this->cleanName($forkDetails['brand_name']) : null,
                'contact_email' => $forkDetails['contact_email'] ?? null,
                'current_platform_version' => $forkDetails['current_platform_version'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            if ($update !== []) {
                $instance->forceFill($update)->save();
            }

            $token = $this->issueToken($instance);

            Auditor::log('white_label.activated', WhiteLabelInstance::class, $instance->id, [
                'brand' => $instance->brand_name,
                'tier' => $instance->tier,
            ]);

            return ['instance' => $instance->fresh(), 'token' => $token];
        });
    }

    /**
     * Admin-driven direct token issuance — the resilience fallback for a fork that
     * cannot reach the activate endpoint (firewalled/air-gapped), mirroring Batch
     * 5's principle that the manual path must always exist. Requires a live license
     * on an active instance; returns the plaintext token once for the admin to hand
     * over out of band.
     */
    public function issueTokenDirectly(WhiteLabelInstance $instance): string
    {
        if (! $instance->hasLiveLicense() || ! $instance->usable()) {
            throw new LicenseActivationException('not_issuable');
        }

        $token = $this->issueToken($instance);

        Auditor::log('white_label.token_issued_directly', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
        ]);

        return $token;
    }

    /** Suspend access temporarily: kill tokens, keep the key live so a restore +
     *  re-activation with the SAME key works. */
    public function suspend(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->tokens()->delete();
        $instance->forceFill([
            'status' => WhiteLabelInstance::SUSPENDED,
            'api_token_last_four' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.suspended', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /** Reverse a suspension. Tokens stay revoked (suspend deleted them) — the fork
     *  re-activates with its still-valid key to get a fresh token. */
    public function restore(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->forceFill([
            'status' => WhiteLabelInstance::ACTIVE,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.restored', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /** Reject a pending registration request. No key is ever issued; kill any
     *  tokens defensively. */
    public function reject(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->tokens()->delete();
        $instance->forceFill([
            'status' => WhiteLabelInstance::REJECTED,
            'api_token_last_four' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.rejected', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /**
     * Permanently revoke the license: the key is dead forever (activateWithKey will
     * refuse it), all tokens die, and the instance is left suspended. Distinct from
     * suspend(), which is reversible with the same key. Use when a subscription
     * ends or a key is compromised beyond re-issue.
     */
    public function revokeLicense(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->tokens()->delete();
        $instance->forceFill([
            'status' => WhiteLabelInstance::SUSPENDED,
            'license_revoked_at' => now(),
            'api_token_last_four' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.license_revoked', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /** Mint a Sanctum token carrying the full scope set and record its last four.
     *  The plaintext token (format {id}|{secret}) is returned once. */
    private function issueToken(WhiteLabelInstance $instance): string
    {
        $instance->tokens()->delete();

        $plain = $instance->createToken($instance->slug ?: 'white-label', WhiteLabelInstance::SCOPES)->plainTextToken;
        $instance->forceFill(['api_token_last_four' => substr($plain, -4)])->save();

        return $plain;
    }

    /** Generate a human-transcribable key NAARA-XXXX-XXXX-XXXX over an
     *  unambiguous alphabet (no 0/O/1/I/L), unique across the table. */
    private function generateUniqueKey(): string
    {
        do {
            $key = 'NAARA-'.$this->block().'-'.$this->block().'-'.$this->block();
        } while (WhiteLabelInstance::where('license_key', $key)->exists());

        return $key;
    }

    private function block(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 30 chars, no 0/O/1/I/L
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    private function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));
        if (! in_array($tier, WhiteLabelInstance::TIERS, true)) {
            Log::warning('white_label.unknown_tier_defaulted', ['given' => $tier]);

            return WhiteLabelInstance::TIER_NORMAL;
        }

        return $tier;
    }

    private function cleanName(string $name): string
    {
        return Str::limit(trim($name) ?: 'White-label brand', 120, '');
    }
}
