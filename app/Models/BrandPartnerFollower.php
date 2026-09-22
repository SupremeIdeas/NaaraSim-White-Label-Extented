<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user "Connecting" to a brand on Naara (owner request, 2026-09-22) — a
 * pure in-platform follow relationship, distinct from following an external
 * social handle (which pays a NaaraCredit reward via SocialFollowClaim).
 */
class BrandPartnerFollower extends Model
{
    public $timestamps = false;

    protected $fillable = ['brand_partner_id', 'user_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
