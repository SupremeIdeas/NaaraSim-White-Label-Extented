<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['translations' => 'array', 'quick' => 'boolean', 'is_active' => 'boolean'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** Copy in the given locale, falling back to the base (English) text. */
    public function copy(string $locale): array
    {
        $t = (array) (($this->translations ?? [])[$locale] ?? []);

        return ['title' => (string) ($t['title'] ?? $this->title), 'body' => (string) ($t['body'] ?? $this->body)];
    }
}
