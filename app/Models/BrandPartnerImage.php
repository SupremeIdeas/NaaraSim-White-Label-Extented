<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A brand's featured image, shown on its Brand Profile page gallery. */
class BrandPartnerImage extends Model
{
    protected $fillable = ['brand_partner_id', 'image_path', 'caption', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
