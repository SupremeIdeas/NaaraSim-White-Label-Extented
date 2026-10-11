<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutCountryRail extends Model
{
    protected $fillable = [
        'country', 'rail', 'provider_supports', 'we_enabled', 'methods', 'currency', 'eta_min_hours', 'eta_max_hours',
        'note_key', 'source_url', 'verified_at', 'verified_by', 'admin_override',
    ];

    protected function casts(): array
    {
        return ['provider_supports' => 'boolean', 'we_enabled' => 'boolean', 'methods' => 'array', 'verified_at' => 'datetime'];
    }
}
