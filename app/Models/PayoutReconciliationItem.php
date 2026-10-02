<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutReconciliationItem extends Model
{
    public $timestamps = false;

    public const MATCHED = 'matched';

    public const MISSING_AT_PROVIDER = 'missing_at_provider';

    public const MISSING_IN_SYSTEM = 'missing_in_system';

    public const AMOUNT_MISMATCH = 'amount_mismatch';

    public const FEE_UNRECORDED = 'fee_unrecorded';

    protected $fillable = ['run_id', 'kind', 'payout_request_id', 'provider_reference', 'our_amount', 'provider_amount', 'provider_fee', 'currency', 'resolved_at', 'resolved_by', 'resolution_note'];

    protected function casts(): array
    {
        return ['our_amount' => 'decimal:4', 'provider_amount' => 'decimal:4', 'provider_fee' => 'decimal:4', 'resolved_at' => 'datetime'];
    }
}
