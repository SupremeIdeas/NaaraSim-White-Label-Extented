<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time follow claim (BUILD-6 §C.3). Exactly one of handle_id /
 * brand_partner_handle_id is set; the unique indexes make the claim unrepeatable.
 */
class SocialFollowClaim extends Model
{
    protected $fillable = [
        'user_id', 'handle_id', 'brand_partner_handle_id', 'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handle(): BelongsTo
    {
        return $this->belongsTo(SocialFollowHandle::class, 'handle_id');
    }

    public function brandPartnerHandle(): BelongsTo
    {
        return $this->belongsTo(BrandPartnerHandle::class, 'brand_partner_handle_id');
    }
}
