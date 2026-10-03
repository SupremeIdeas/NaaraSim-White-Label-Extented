<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A run of the read-only money-invariants checker (Addendum D-3.8). */
class PayoutInvariantRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['started_at', 'finished_at', 'status', 'violation_count', 'results', 'triggered_by'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'results' => 'array'];
    }
}
