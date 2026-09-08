<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A linked social sign-in identity for a user (owner request). */
class SocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_id', 'avatar'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
