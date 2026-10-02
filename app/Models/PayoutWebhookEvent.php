<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One provider webhook delivery. unique(provider, provider_event_id) makes a duplicate a no-op (Addendum D-3.3). */
class PayoutWebhookEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider', 'provider_event_id', 'request_reference', 'event_type', 'received_at',
        'processed_at', 'outcome', 'payload_hash', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'raw_payload' => 'encrypted',
        ];
    }
}
