<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff member's profit-share compensation setting (NAARA-BUILD-23). The rate
 * is admin-set; effective_from makes a change non-retroactive (§1.1).
 */
class StaffCompensationProfile extends Model
{
    protected $fillable = ['user_id', 'profit_share_pct', 'is_active', 'effective_from'];

    protected function casts(): array
    {
        return [
            'profit_share_pct' => 'decimal:3',
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
