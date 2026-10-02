<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One escrowed member-to-member earnings transfer (see EarningsTransferService). */
class EarningsTransfer extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected $fillable = ['reference', 'sender_id', 'recipient_id', 'source_bucket', 'amount_usd', 'note', 'status', 'expires_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['amount_usd' => 'decimal:4', 'expires_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
