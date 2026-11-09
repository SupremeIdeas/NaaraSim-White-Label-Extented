<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A number whose network we have CONFIRMED through a delivered top-up (beats the prefix table, so ported numbers resolve correctly). */
class TopupNumberOperator extends Model
{
    protected $fillable = ['phone_e164', 'country_iso', 'operator_name', 'confirmations', 'last_confirmed_at'];

    protected function casts(): array
    {
        return ['last_confirmed_at' => 'datetime'];
    }
}
