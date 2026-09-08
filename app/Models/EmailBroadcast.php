<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A record of one admin email broadcast (Email Studio §3.2). */
class EmailBroadcast extends Model
{
    protected $fillable = [
        'subject', 'body_html', 'audience_type', 'audience_value',
        'audience_label', 'recipient_count', 'status', 'sent_by',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
