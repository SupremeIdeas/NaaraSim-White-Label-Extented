<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Auditable priority-score adjustment (BUILD-9 §6.6). */
class BrandPriorityLog extends Model
{
    protected $table = 'brand_priority_log';

    protected $fillable = [
        'brand_partner_id', 'handle_id', 'month', 'guaranteed', 'actual',
        'adjustment', 'new_priority_score',
    ];
}
