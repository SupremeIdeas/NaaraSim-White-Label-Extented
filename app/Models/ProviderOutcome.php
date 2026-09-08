<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * NAARA-BUILD-15 — one recorded provider purchase attempt (success|failure).
 * Written by CircuitBreaker::record() from inside each router's existing loop;
 * read to compute rolling reliability + drive circuit-breaker transitions, and
 * (BUILD-16) to feed NCI's scoring.
 */
class ProviderOutcome extends Model
{
    public const SUCCESS = 'success';

    public const FAILURE = 'failure';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
