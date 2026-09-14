<?php

namespace App\Services\Updater;

use App\Exceptions\LicenseActivationException;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePayment;
use App\Models\WhiteLabelLicensePlan;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Wallet\WalletService;
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
    public function __construct(
        private readonly WalletService $wallet = new WalletService,
        private readonly PlatformEarningsService $earnings = new PlatformEarningsService,
    ) {}

    /**
     * Self-serve registration REQUEST: records a would-be subscriber as a pending
     * instance with no key and no token. An admin reviews it and issues a license
     * (issueLicense) — mirrors Merchant's pending → active review trail. Idempotent
     * on slug is NOT assumed here; the caller validates uniqueness first.
     *
     * Prompt 21-EXT §2/§6 — a Merchant-V2 self-service request additionally carries
     * `merchant_id`, `license_plan_id` (the chosen carousel card), `requested_tier`
     * (derived from that plan's own `tier`, never typed separately), and the
     * hosting preference/disclaimer fields. The resell-status flag for the
     * requested tier is checked here too — server-side, never trusting that the
     * carousel already hid a closed tier (defense in depth, same principle this
     * codebase applies everywhere else).
     *
     * @throws \RuntimeException when the requested tier's resell status is closed.
     */
    public function register(array $details): WhiteLabelInstance
    {
        $requestedTier = $details['requested_tier'] ?? null;
        if ($requestedTier !== null && ! WhiteLabelLicensePlan::resellOpenForTier($this->normalizeTier($requestedTier))) {
            throw new \RuntimeException('resell_closed');
        }

        $instance = WhiteLabelInstance::create([
            'brand_name' => $this->cleanName($details['brand_name'] ?? 'White-label brand'),
            'slug' => $details['slug'] ?? Str::slug(($details['brand_name'] ?? 'brand').'-'.Str::random(6)),
            'contact_email' => $details['contact_email'] ?? '',
            'owner_user_id' => $details['owner_user_id'] ?? null,
            'merchant_id' => $details['merchant_id'] ?? null,
            'license_plan_id' => $details['license_plan_id'] ?? null,
            'acquisition_method' => $details['acquisition_method'] ?? WhiteLabelInstance::ACQUISITION_ADMIN_PROVISIONED,
            'requested_tier' => $requestedTier,
            'hosting_preference' => $details['hosting_preference'] ?? null,
            'hosting_disclaimer_acknowledged_at' => ! empty($details['hosting_disclaimer_acknowledged']) ? now() : null,
            'registration_note' => isset($details['note']) ? Str::limit((string) $details['note'], 2000, '') : null,
            'status' => WhiteLabelInstance::PENDING,
        ]);

        Auditor::log('white_label.registered', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'slug' => $instance->slug,
            'acquisition_method' => $instance->acquisition_method,
        ]);

        return $instance;
    }

    /**
     * Prompt 21-EXT §3.1 — admin sets (or confirms, pre-filled from the chosen
     * plan) the price on a pending self-service request. Deliberately does NOT
     * issue a license: the instance stays PENDING until the merchant actually
     * pays via payAndActivate(). Purely a review-trail/pricing action — no
     * money moves here.
     */
    public function priceForPayment(WhiteLabelInstance $instance, float $priceUsd, ?int $reviewerId = null): WhiteLabelInstance
    {
        $instance->forceFill([
            'price_usd' => round($priceUsd, 2),
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.priced_for_payment', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'price_usd' => $instance->price_usd,
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
            // Batch 8: a freshly-issued license starts at the level its tier
            // implies — Extended fully unlocked, Normal locked-down until paid.
            'entitlement_level' => WhiteLabelInstance::defaultLevelForTier($tier),
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
            'entitlement_level' => $instance->entitlement_level,
        ]);

        return $instance;
    }

    /**
     * Prompt 21-EXT §3.3 — upgrade a LIVE instance's tier (normal → extended)
     * without re-issuing its license key or touching its Sanctum tokens.
     * Deliberately NOT `issueLicense()`: that method regenerates the key and
     * deletes every live token on every call, which is correct for a genuinely
     * fresh license but would break an already-deployed fork's working
     * connection at the exact moment its owner finishes paying off the balance
     * to Extended. Modeled on setEntitlementLevel()'s existing non-destructive
     * shape — a tier/entitlement change without touching credentials is
     * already a normal, supported operation in this codebase.
     */
    public function upgradeTier(WhiteLabelInstance $instance, string $newTier): WhiteLabelInstance
    {
        $newTier = $this->normalizeTier($newTier);

        $instance->forceFill([
            'tier' => $newTier,
            'entitlement_level' => WhiteLabelInstance::defaultLevelForTier($newTier),
        ])->save();

        Auditor::log('white_label.tier_upgraded', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'tier' => $newTier,
            'entitlement_level' => $instance->entitlement_level,
        ]);

        return $instance;
    }

    /**
     * Prompt 21-EXT §3.2/§5.2 — the self-service instance's FIRST payment.
     * Only reachable once an admin has set price_usd (Prompt 21 §4.2) — never
     * charges an unpriced, unreviewed request. Reuses WalletService::charge()
     * exactly (money-safety rule 1.2's refund-on-failure applies unmodified);
     * inside the closure, issues the license exactly as the admin-provisioned
     * path already does, records the payment ledger row, and credits the
     * platform earnings bucket for the full amount (a flat product sale, no
     * underlying cost to net out).
     *
     * @throws \App\Exceptions\InsufficientBalanceException
     */
    public function payAndActivate(WhiteLabelInstance $instance, User $payer, ?int $reviewerId = null): WhiteLabelInstance
    {
        if ($instance->price_usd === null) {
            throw new LicenseActivationException('not_priced');
        }
        if ($instance->hasLiveLicense()) {
            throw new LicenseActivationException('already_licensed');
        }

        $amount = (float) $instance->price_usd;
        $tier = $this->normalizeTier($instance->requested_tier ?? WhiteLabelInstance::TIER_NORMAL);
        $ref = 'wl-license:'.$instance->id.':initial:'.now()->timestamp;

        return $this->wallet->charge($payer, $amount, 'USD', function () use ($instance, $tier, $reviewerId, $amount, $ref) {
            $this->issueLicense($instance, $tier, $reviewerId);
            $instance->forceFill(['payment_reference' => $ref])->save();

            WhiteLabelLicensePayment::create([
                'white_label_instance_id' => $instance->id,
                'amount_usd' => $amount,
                'kind' => WhiteLabelLicensePayment::KIND_INITIAL,
                'payment_reference' => $ref,
            ]);
            $this->earnings->accrue($amount, 'white_label_license', 'plat-earn:'.$ref, 'White-label license — '.$instance->brand_name);
            $this->checkResellAutoClose();

            return $instance->fresh();
        }, ['reference' => $ref, 'description' => 'White-label license: '.$instance->brand_name]);
    }

    /**
     * Prompt 21-EXT §3.3 — the balance-completion payment for a `normal`-tier
     * self-service instance. A SECOND, independent WalletService::charge()
     * from the first — same refund-on-failure guarantee. On success, upgrades
     * the tier via upgradeTier() (non-destructive — see that method's own
     * docblock), never issueLicense() (which would kill the fork's live
     * token). Amount is always the true remaining balance against the active
     * Extended plan's current price, never a stale/typed figure.
     *
     * @throws \App\Exceptions\InsufficientBalanceException
     */
    public function payBalanceAndUpgrade(WhiteLabelInstance $instance, User $payer, float $amount): WhiteLabelInstance
    {
        if (! $instance->hasLiveLicense() || $instance->tier !== WhiteLabelInstance::TIER_NORMAL) {
            throw new LicenseActivationException('not_upgradeable');
        }

        $ref = 'wl-license:'.$instance->id.':balance:'.now()->timestamp;

        return $this->wallet->charge($payer, $amount, 'USD', function () use ($instance, $amount, $ref) {
            $this->upgradeTier($instance, WhiteLabelInstance::TIER_EXTENDED);

            WhiteLabelLicensePayment::create([
                'white_label_instance_id' => $instance->id,
                'amount_usd' => $amount,
                'kind' => WhiteLabelLicensePayment::KIND_BALANCE_COMPLETION,
                'payment_reference' => $ref,
            ]);
            $this->earnings->accrue($amount, 'white_label_license', 'plat-earn:'.$ref, 'White-label license balance — '.$instance->brand_name);
            $this->checkResellAutoClose();

            return $instance->fresh();
        }, ['reference' => $ref, 'description' => 'White-label license balance: '.$instance->brand_name]);
    }

    /**
     * Raise (or lower) a live instance's feature-entitlement level (Batch 8) —
     * the operator's "mark this Normal fork as paid" action, moving it from
     * `basic` to `standard` (or any admin-chosen level). Purely a feature-lock
     * change: it never touches the token, the key, or the package tier, so the
     * fork keeps working and simply unlocks more features on its next check-in.
     */
    public function setEntitlementLevel(WhiteLabelInstance $instance, string $level): WhiteLabelInstance
    {
        $level = in_array($level, WhiteLabelInstance::LEVELS, true) ? $level : WhiteLabelInstance::LEVEL_BASIC;

        $instance->forceFill(['entitlement_level' => $level])->save();

        Auditor::log('white_label.entitlement_level_set', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'entitlement_level' => $level,
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

    /**
     * Prompt 21-EXT §6.3 — after a self-service sale actually lands (license
     * issued or upgraded, never at request time), check whether a resell
     * threshold was just crossed and auto-close it. Plain counts against
     * existing WhiteLabelInstance rows (§0.8) — no new counter column, no
     * scheduled job. An admin can always manually flip a flag back to true
     * regardless of how it closed (Setting is the single source of truth).
     */
    private function checkResellAutoClose(): void
    {
        if (WhiteLabelLicensePlan::soldCountForTier(WhiteLabelInstance::TIER_NORMAL) >= 200) {
            Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);
        }

        if (WhiteLabelLicensePlan::soldCountForTier(WhiteLabelInstance::TIER_EXTENDED) >= 2000) {
            Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);
            Setting::setValue(WhiteLabelLicensePlan::SETTING_EXTENDED_OPEN, false);
        }
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
