<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One identity-verification attempt (ROADMAP §Layer 0.3). A user is "at" a level
 * once they hold an approved row for it. L2 gates withdrawals; L3 (KYB) gates
 * becoming a merchant.
 */
class KycVerification extends Model
{
    public const L2 = 2; // individual: ID + liveness

    public const L3 = 3; // business: KYB documents

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const FAILED = 'failed';

    protected $fillable = [
        'user_id', 'level', 'provider', 'status', 'checks', 'reference',
        'reason', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'checks' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::APPROVED, self::REJECTED, self::FAILED], true);
    }
}
