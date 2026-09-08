<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an AI pricing proposal: the current vs proposed retail for a plan,
 * with the guard floor and projected profit. cost_usd / floor_usd are admin-only
 * (this model is only ever read behind the admin gate) and never serialised to a
 * user-facing payload.
 */
class PricingProposalLine extends Model
{
    protected $fillable = [
        'proposal_id', 'item_type', 'plan_id', 'name',
        'cost_usd', 'current_retail_usd', 'proposed_retail_usd', 'floor_usd',
        'projected_profit_usd', 'projected_margin_pct', 'guard_applied', 'accepted', 'rationale',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'current_retail_usd' => 'decimal:4',
            'proposed_retail_usd' => 'decimal:4',
            'floor_usd' => 'decimal:4',
            'projected_profit_usd' => 'decimal:4',
            'projected_margin_pct' => 'decimal:2',
            'guard_applied' => 'boolean',
            'accepted' => 'boolean',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PricingProposal::class, 'proposal_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EsimPlan::class, 'plan_id');
    }
}
