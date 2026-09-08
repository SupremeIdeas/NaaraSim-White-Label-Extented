<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An immutable published snapshot of a page's sections (Section Builder §3).
 * The row flagged is_live is what the public renderer serves; older rows are the
 * version history the admin can roll back to.
 */
class PageSectionVersion extends Model
{
    protected $fillable = [
        'page_key', 'snapshot', 'label', 'is_live', 'published_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'is_live' => 'boolean',
        ];
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
