<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A number blocked platform-wide from being dialed (Prompt 11). */
class SpamBlockedCaller extends Model
{
    protected $fillable = [
        'msisdn',
        'phone_number',
        'report_count_at_block',
        'source',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
        ];
    }
}
