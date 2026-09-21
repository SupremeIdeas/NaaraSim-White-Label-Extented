<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /** Tier 5 #11 Phase A1 — the two selectable presentation styles. */
    public const STYLES = ['banner_hero', 'dark_feature'];

    protected $fillable = [
        'title', 'body', 'icon', 'style', 'image_path', 'feature_image_path',
        'bullets', 'cta_label', 'cta_url', 'secondary_label', 'secondary_url',
        'coupon_code', 'audience', 'recipients', 'status', 'created_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'recipients' => 'integer', 'bullets' => 'array'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The in-app payload every recipient receives. If a coupon is attached, the
     * CTA points to the catalogue with a one-click claim; otherwise it uses the
     * admin's own CTA (or none). Never carries anything sensitive.
     *
     * Tier 5 #11 Phase A1 — carries the chosen presentation style plus its
     * style-specific fields (image/feature image/bullets/secondary link) so
     * the notification surface can render the real `banner_hero`/
     * `dark_feature` treatment instead of a generic row.
     *
     * @return array{category:string, icon:string, style:string, title:string, body:string, image_url:?string, feature_image_url:?string, bullets:array, action_url:?string, action_label:?string, secondary_label:?string, secondary_url:?string}
     */
    public function toInApp(): array
    {
        [$url, $label] = $this->resolveCta();

        return [
            'category' => 'offer',
            'icon' => $this->icon ?: 'gift',
            'style' => in_array($this->style, self::STYLES, true) ? $this->style : 'banner_hero',
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_path,
            'feature_image_url' => $this->feature_image_path,
            'bullets' => $this->bullets ?: [],
            'action_url' => $url,
            'action_label' => $label,
            'secondary_label' => $this->secondary_label,
            'secondary_url' => $this->secondary_url,
        ];
    }

    /** @return array{0:?string,1:?string} [url, label] */
    private function resolveCta(): array
    {
        if ($this->coupon_code) {
            // One-click claim: land on the catalogue with the code stashed so the
            // next purchase pre-fills it.
            return [url('/catalogue?claim='.urlencode($this->coupon_code)), $this->cta_label ?: 'Claim offer'];
        }
        if ($this->cta_url) {
            return [$this->cta_url, $this->cta_label ?: 'Learn more'];
        }

        return [null, null];
    }
}
