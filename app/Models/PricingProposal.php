<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AI pricing proposal (Plan Price with Claude). Claude proposes; the admin
 * approves; MarginGuard governs. A proposal is a snapshot of what SHOULD change,
 * never a live price — applying it re-runs the guards through PricingEngine.
 */
class PricingProposal extends Model
{
    protected $fillable = [
        'status', 'model_used', 'summary', 'meta',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return HasMany<PricingProposalLine> */
    public function lines(): HasMany
    {
        return $this->hasMany(PricingProposalLine::class, 'proposal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
