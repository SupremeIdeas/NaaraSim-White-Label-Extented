<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Crash-safe intent log for one provider submit (Addendum C fix A). Written BEFORE
 * the HTTP call and advanced afterwards, so a timeout / 5xx / dead worker leaves a
 * row in `submitted` or `unknown` — never a silent gap that looks like a failure.
 */
class PayoutProviderCall extends Model
{
    public const INTENT = 'intent';

    public const SUBMITTED = 'submitted';

    public const CONFIRMED = 'confirmed';

    public const UNKNOWN = 'unknown';

    public const DEFINITIVELY_FAILED = 'definitively_failed';

    public $timestamps = false;

    protected $fillable = [
        'payout_request_id', 'provider', 'attempt', 'idempotency_key', 'state', 'http_status',
        'provider_ref', 'error', 'started_at', 'finished_at', 'next_lookup_at', 'lookup_attempts',
        'not_found_count', 'first_not_found_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'finished_at' => 'datetime', 'next_lookup_at' => 'datetime',
            'first_not_found_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class, 'payout_request_id');
    }
}
