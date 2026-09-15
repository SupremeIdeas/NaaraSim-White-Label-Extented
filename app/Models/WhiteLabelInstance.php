<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * A registered white-label brand instance (Batch 4 §1). It IS the Sanctum
 * tokenable for every request its deployed copy makes back to the master
 * platform — exactly like ApiClient is for the Developer API — so `auth:sanctum`
 * resolves a WhiteLabelInstance (not a User) on the white-label distribution
 * API, and its token abilities are its scopes.
 *
 * Status/tier/review-trail mirror Merchant. Batch 6 (License Authority) adds the
 * license-key + token-issuance flow on top of this same token system: an admin
 * issues a license (key + tier + active status), the deployed fork exchanges that
 * key for a Sanctum token at the activate endpoint, and revocation/suspension
 * kill the token. Batch 7 makes `tier` gate real package entitlement via an
 * ordering rather than exact match.
 */
class WhiteLabelInstance extends Model
{
    use HasApiTokens;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const REJECTED = 'rejected';

    /**
     * License tiers, cheapest → richest. The ORDER of this list is the entitlement
     * ordering Batch 7 enforces: a package requiring a given tier is available to
     * that tier and every richer one (extended ⊇ normal). Keep it ordered.
     */
    public const TIER_NORMAL = 'normal';

    public const TIER_EXTENDED = 'extended';

    public const TIERS = [self::TIER_NORMAL, self::TIER_EXTENDED];

    /**
     * Feature-entitlement levels (Batch 8), cheapest → richest. Orthogonal to
     * `tier`: `tier` decides which PACKAGES a fork may download, `entitlement_level`
     * decides which FEATURES its users may use (see App\Support\FeatureLocks).
     *   - basic    — Normal fork pre-payment (most features locked).
     *   - standard — Normal fork post-payment (only the richest features locked).
     *   - full     — Extended fork (nothing locked).
     */
    public const LEVEL_BASIC = 'basic';

    public const LEVEL_STANDARD = 'standard';

    public const LEVEL_FULL = 'full';

    public const LEVELS = [self::LEVEL_BASIC, self::LEVEL_STANDARD, self::LEVEL_FULL];

    /** Sanctum token abilities a white-label instance may hold. Every tier gets
     *  the full set — tier gates WHICH PACKAGES are eligible (PackageDistribution),
     *  not which endpoints can be reached. */
    public const SCOPES = ['updates.check', 'updates.download', 'themes.check', 'themes.download'];

    /** Prompt 21 §2.1 — how this instance came to exist. Every pre-existing
     *  row defaults to admin_provisioned via the adding migration, so the
     *  current admin-driven flow's data is never reinterpreted. */
    public const ACQUISITION_ADMIN_PROVISIONED = 'admin_provisioned';

    public const ACQUISITION_MERCHANT_SELF_SERVICE = 'merchant_self_service';

    /** Prompt 21 §2.3 / 21-EXT2 §1 — the merchant's hosting choice. `own_server`
     *  (a single generic option) is superseded by two concrete paths, each with
     *  its own recommended provider and credential-collection flow in the
     *  post-purchase project intake form. */
    public const HOSTING_SUPREME_IDEAS_SERVER = 'supreme_ideas_server';

    public const HOSTING_OWN_VPS = 'own_vps';

    public const HOSTING_OWN_SHARED = 'own_shared';

    public const HOSTING_CHOICES = [self::HOSTING_SUPREME_IDEAS_SERVER, self::HOSTING_OWN_VPS, self::HOSTING_OWN_SHARED];

    protected $fillable = [
        'brand_name',
        'slug',
        'contact_email',
        'owner_user_id',
        'merchant_id',
        'license_plan_id',
        'status',
        'acquisition_method',
        'tier',
        'requested_tier',
        'entitlement_level',
        'hosting_preference',
        'hosting_disclaimer_acknowledged_at',
        'price_usd',
        'theme_addon',
        'theme_addon_price_usd',
        'payment_reference',
        'license_key',
        'api_token_last_four',
        'license_issued_at',
        'license_revoked_at',
        'registration_note',
        'current_platform_version',
        'last_checked_in_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = [
        'license_key',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_in_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'license_issued_at' => 'datetime',
            'license_revoked_at' => 'datetime',
            'hosting_disclaimer_acknowledged_at' => 'datetime',
            'price_usd' => 'decimal:2',
            'theme_addon_price_usd' => 'decimal:2',
        ];
    }

    public function hasThemeAddon(): bool
    {
        return $this->theme_addon !== null && $this->theme_addon !== \App\Support\ThemeAddonCatalog::NONE;
    }

    /** The full amount due at payAndActivate() — the license price plus
     *  whatever custom-theme add-on was requested alongside it, if any. */
    public function totalDueUsd(): ?float
    {
        if ($this->price_usd === null) {
            return null;
        }

        return round((float) $this->price_usd + (float) ($this->theme_addon_price_usd ?? 0), 2);
    }

    /** True when this instance is allowed to talk to the API at all. */
    public function usable(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** True once a license key has been issued and not revoked. */
    public function hasLiveLicense(): bool
    {
        return $this->license_key !== null && $this->license_revoked_at === null;
    }

    /**
     * The numeric rank of this instance's tier within the entitlement ordering,
     * or null when untiered. A higher rank includes everything a lower one grants.
     */
    public function tierRank(): ?int
    {
        return self::rankOf($this->tier);
    }

    /** Rank of a tier string within TIERS (0-based), or null if unknown/untiered. */
    public static function rankOf(?string $tier): ?int
    {
        if ($tier === null || $tier === '') {
            return null;
        }

        $index = array_search($tier, self::TIERS, true);

        return $index === false ? null : $index;
    }

    /**
     * The feature-entitlement level a freshly-issued license of the given tier
     * starts at (Batch 8): an Extended fork is fully unlocked from day one; a
     * Normal fork starts locked-down (basic) and the operator raises it to
     * `standard` once the buyer has paid up. An unknown/null tier gets the
     * safest paid-nothing-yet default.
     */
    public static function defaultLevelForTier(?string $tier): string
    {
        return $tier === self::TIER_EXTENDED ? self::LEVEL_FULL : self::LEVEL_BASIC;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(WhiteLabelApiLog::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function licensePlan(): BelongsTo
    {
        return $this->belongsTo(WhiteLabelLicensePlan::class, 'license_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WhiteLabelLicensePayment::class, 'white_label_instance_id');
    }

    /** Prompt 21-EXT §4 — the single source of truth for how much has
     *  actually been paid toward this instance's license, summed from its
     *  own payment ledger rather than re-derived from price_usd (which is
     *  only ever what was CHARGED for the initial purchase, not a running
     *  total once a balance-completion payment follows it). */
    /**
     * Sum of LICENSE payments only (initial + balance-completion) — the
     * figure the Normal→Extended balance-completion math is built on.
     * Deliberately excludes theme_addon payments: a custom-theme add-on is
     * a separate purchase and must never let a merchant pay their way to
     * Extended early just by picking an expensive theme tier (money-safety
     * rule 1 — retail is never silently discounted).
     */
    public function amountPaidTotal(): float
    {
        return round((float) $this->payments()
            ->whereIn('kind', [WhiteLabelLicensePayment::KIND_INITIAL, WhiteLabelLicensePayment::KIND_BALANCE_COMPLETION])
            ->sum('amount_usd'), 2);
    }

    /** Prompt 21-EXT2 §2 — the current project-commencement brief, one per
     *  instance (a resubmission overwrites the draft, it isn't versioned). */
    public function intake(): HasOne
    {
        return $this->hasOne(WhiteLabelProjectIntake::class);
    }
}
