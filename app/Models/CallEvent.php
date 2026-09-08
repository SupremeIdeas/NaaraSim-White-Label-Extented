<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An inbound-call activity record on a forwarded number (Live Voice — Part A). */
class CallEvent extends Model
{
    protected $fillable = [
        'user_id', 'call_forwarding_rule_id', 'call_sid', 'from_number', 'to_number', 'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
