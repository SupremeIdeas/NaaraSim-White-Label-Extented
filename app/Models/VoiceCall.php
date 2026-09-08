<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An outbound in-browser (WebRTC) call (Live Voice — Part B). The wallet is
 * charged upfront for a funded block of minutes and settled on hang-up.
 *
 * Money-safety rule 1.2: provider_rate (wholesale per-minute cost) and the raw
 * provider name are hidden — the user only ever sees retail_per_min / Naara Line.
 */
class VoiceCall extends Model
{
    public const STATUS_CONNECTING = 'connecting';

    public const STATUS_IN_PROGRESS = 'in-progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_ANSWER = 'no-answer';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'destination', 'provider', 'call_sid', 'status',
        'retail_per_min', 'provider_rate', 'minutes_authorized', 'amount_held',
        'hold_reference', 'minutes_billed', 'amount_charged', 'refunded',
        'duration_seconds', 'settled_at',
    ];

    protected $hidden = [
        'provider_rate',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'retail_per_min' => 'decimal:4',
            'provider_rate' => 'decimal:4',
            'amount_held' => 'decimal:4',
            'amount_charged' => 'decimal:4',
            'refunded' => 'decimal:4',
            'settled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
