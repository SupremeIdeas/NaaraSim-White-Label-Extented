<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prompt 21-EXT §4 — one row per successful charge against a self-service
 * WhiteLabelInstance license. amount_paid_total for an instance is
 * SUM(amount_usd) over its own rows here — the single source of truth the
 * balance-completion math and the resell-status threshold counts both read.
 */
class WhiteLabelLicensePayment extends Model
{
    public const KIND_INITIAL = 'initial';

    public const KIND_BALANCE_COMPLETION = 'balance_completion';

    protected $fillable = [
        'white_label_instance_id', 'amount_usd', 'kind', 'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhiteLabelInstance::class, 'white_label_instance_id');
    }
}
