<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A row in a staff member's profit-share earnings ledger (NAARA-BUILD-23). */
class StaffEarning extends Model
{
    public const ACCRUAL = 'accrual';

    public const HOLD = 'hold';

    public const RELEASE = 'release';

    protected $fillable = [
        'user_id', 'type', 'amount', 'balance_after', 'currency',
        'reference', 'period_start', 'period_end', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
