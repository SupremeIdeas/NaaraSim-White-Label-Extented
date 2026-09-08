<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One editable DRAFT section on a builder page (Section Builder prompt §2).
 * `type` selects the renderer + config schema (see App\Support\SectionLibrary);
 * `config` is the per-type settings blob. Public pages read published snapshots
 * (PageSectionVersion), never these rows directly.
 */
class PageSection extends Model
{
    protected $fillable = [
        'page_key', 'type', 'config', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
