<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-uploaded imagery for a single country in the eSIM navigation grid
 * (BUILD-8 §2.2). Keyed on ISO2 (uppercase). Both image columns are public URLs
 * produced by MediaStorage; either may be null (clean fallback in the UI).
 */
class EsimCountryImage extends Model
{
    protected $fillable = ['country_code', 'icon_path', 'detail_image_path'];

    /** Normalise the key to uppercase ISO2 on the way in. */
    public function setCountryCodeAttribute(?string $value): void
    {
        $this->attributes['country_code'] = strtoupper(trim((string) $value));
    }
}
