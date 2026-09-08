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
 * Status/tier/review-trail mirror Merchant. Batch 6 adds license-key issuance
 * and license-specific scopes on top of this same token system; Batch 7
 * populates `tier` via a real payment flow.
 */
class WhiteLabelInstance extends Model
{
    use HasApiTokens;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const REJECTED = 'rejected';

    /** Sanctum token abilities a white-label instance may hold (this batch). */
    public const SCOPES = ['updates.check', 'updates.download', 'themes.check', 'themes.download'];

    protected $fillable = [
        'brand_name',
        'slug',
        'contact_email',
        'owner_user_id',
        'status',
        'tier',
        'current_platform_version',
        'last_checked_in_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_in_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** True when this instance is allowed to talk to the API at all. */
    public function usable(): bool
    {
        return $this->status === self::ACTIVE;
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
