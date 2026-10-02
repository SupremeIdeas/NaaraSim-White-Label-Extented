<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A payee who may not create or send payouts until released (Addendum D-3.5). */
class PayoutUserFreeze extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'reason', 'frozen_by', 'note', 'frozen_at', 'released_at'];

    protected function casts(): array
    {
        return ['frozen_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
