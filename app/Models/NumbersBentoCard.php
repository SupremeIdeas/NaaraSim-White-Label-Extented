<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single admin-editable Numbers landing bento card (Numbers V6 §2).
 */
class NumbersBentoCard extends Model
{
    protected $fillable = [
        'key', 'badge_label', 'icon_path', 'image_path',
        'title', 'subtitle', 'bullets', 'sort_order', 'is_active', 'display_mode',
    ];

    protected function casts(): array
    {
        return [
            'bullets' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
