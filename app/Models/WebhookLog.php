<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'payload',
        'signature',
        'verified',
        'processed',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'verified' => 'boolean',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
