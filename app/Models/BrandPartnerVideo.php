<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A brand's premium video preview (BUILD-9 §2.4). */
class BrandPartnerVideo extends Model
{
    protected $fillable = ['brand_partner_id', 'video_url', 'platform', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** A privacy-friendly embed URL, or null if the id can't be parsed. */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;
        if ($this->platform === 'youtube') {
            if (preg_match('#(?:v=|youtu\.be/|/embed/|/shorts/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
                return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1&rel=0';
            }
        } elseif ($this->platform === 'vimeo') {
            if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
                return 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1';
            }
        }

        return null;
    }
}
