<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One immutable Payout Guardian decision. Append-only — never updated or deleted. */
class PayoutDecision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payout_request_id', 'attempt', 'decision', 'reason', 'score', 'rules', 'engine_version', 'shadow', 'qa_sampled',
        'decided_by', 'decided_at', 'next_check_at',
    ];

    protected function casts(): array
    {
        return ['rules' => 'array', 'shadow' => 'boolean', 'qa_sampled' => 'boolean', 'decided_at' => 'datetime', 'next_check_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('payout_decisions is append-only.'));
        static::deleting(fn () => throw new \LogicException('payout_decisions is append-only.'));
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class, 'payout_request_id');
    }
}
