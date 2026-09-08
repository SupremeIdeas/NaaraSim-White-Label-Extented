<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Promo banner (Module 31). Admin uploads artwork per placement zone; the
 * user side renders active banners as a responsive carousel (dashboard home)
 * or a single card (menu sheet / account page).
 */
class Banner extends Model
{
    /** placement => [label, desktop WxH, mobile WxH, aspect class] */
    public const PLACEMENTS = [
        'dashboard_home' => ['Dashboard home (carousel)', '1200×400', '800×400'],
        'menu_sheet' => ['Mobile “More” menu', '800×400', '800×400'],
        'account' => ['Account / profile settings', '800×400', '800×400'],
    ];

    protected $fillable = [
        'title',
        'placement',
        'image_url',
        'image_url_mobile',
        'video_url',
        'link_url',
        'coupon_id',
        'sort_order',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** A motion banner — plays a muted-looping video (image_url is the poster). */
    public function hasVideo(): bool
    {
        return filled($this->video_url);
    }

    /** Currently visible to users: active and inside its schedule window. */
    public function scopeLive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** External links open in a new tab; internal paths navigate in-app. */
    public function isExternalLink(): bool
    {
        return $this->link_url !== null && preg_match('#^https?://#i', $this->link_url) === 1
            && ! str_starts_with($this->link_url, rtrim(config('app.url'), '/'));
    }
}
