<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $fillable = [
        'brand_name',
        'slug',
        'contact_email',
        'owner_user_id',
        'status',
        'tier',
        'entitlement_level',
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
        ];
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
}
