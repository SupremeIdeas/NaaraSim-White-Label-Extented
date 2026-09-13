<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A permanent number pulled for abuse/complaint and barred from re-sale
 * (Prompt 11 recycled-number pre-check). `provider` is PRIVATE (supplier
 * masking, money-rule 1.2) so it never reaches a user-facing payload.
 */
class BlockedNumber extends Model
{
    protected $fillable = [
        'msisdn',
        'phone_number',
        'provider',
        'reason',
        'source',
        'blocked_by',
    ];

    protected $hidden = [
        'provider',
    ];
}
