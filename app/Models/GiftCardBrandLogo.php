<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One stored, normalised logo per gift-card brand (Logo Wizard). Never hot-linked: `path` is a file on the media disk. */
class GiftCardBrandLogo extends Model
{
    public const APPROVED = 'approved';

    public const REVIEW = 'review';

    public const FALLBACK = 'fallback';

    protected $fillable = ['brand_key', 'brand_name', 'path', 'source', 'source_url', 'domain', 'sha256', 'confidence', 'status', 'fetched_at'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
