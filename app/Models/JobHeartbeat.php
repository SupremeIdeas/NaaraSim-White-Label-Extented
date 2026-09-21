<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tier 4 #10 Phase B1 — one recorded run of a scheduled command. See
 * App\Support\JobHeartbeats for the write/read API; nothing else should
 * write to this table directly.
 */
class JobHeartbeat extends Model
{
    protected $fillable = ['job_name', 'started_at', 'finished_at', 'duration_ms', 'outcome', 'detail'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
