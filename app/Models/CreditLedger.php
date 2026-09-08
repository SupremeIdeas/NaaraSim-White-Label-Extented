<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One NaaraCredit movement — the credit audit trail (loyalty module). */
class CreditLedger extends Model
{
    protected $table = 'credit_ledger';

    protected $fillable = [
        'user_id', 'type', 'source', 'withdrawable', 'amount', 'balance_after', 'reference', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'withdrawable' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
