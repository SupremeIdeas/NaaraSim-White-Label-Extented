<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One user's spam report on a phone number (Prompt 11). */
class SpamReport extends Model
{
    protected $fillable = [
        'msisdn',
        'phone_number',
        'reporter_user_id',
        'reason',
        'source',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }
}
