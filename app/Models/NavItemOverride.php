<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One admin-set position/visibility override for a bottom-nav catalog item.
 * See App\Support\BottomNav for the catalog, eligibility rules, and how this
 * is merged on top of them.
 */
class NavItemOverride extends Model
{
    protected $fillable = ['nav', 'item_key', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
