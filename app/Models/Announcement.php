<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'body', 'icon', 'cta_label', 'cta_url', 'coupon_code',
        'audience', 'recipients', 'status', 'created_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'recipients' => 'integer'];
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
     * @return array{category:string, icon:string, title:string, body:string, action_url:?string, action_label:?string}
     */
    public function toInApp(): array
    {
        [$url, $label] = $this->resolveCta();

        return [
            'category' => 'offer',
            'icon' => $this->icon ?: 'gift',
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $url,
            'action_label' => $label,
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
