<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-uploaded imagery for a region / sub-region (and the global tile) in the
 * eSIM navigation grid (BUILD-8 §2.3). Keyed on region_slug (e.g. 'europe',
 * 'world'). Both image columns are public URLs produced by MediaStorage; either
 * may be null (clean fallback in the UI).
 */
class EsimRegionImage extends Model
{
    protected $fillable = ['region_slug', 'icon_path', 'detail_image_path'];

    /** Normalise the key to a lowercase slug on the way in. */
    public function setRegionSlugAttribute(?string $value): void
    {
        $this->attributes['region_slug'] = \Illuminate\Support\Str::slug(trim((string) $value));
    }
}
