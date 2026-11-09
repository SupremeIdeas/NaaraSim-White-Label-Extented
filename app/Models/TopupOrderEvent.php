<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only audit trail of everything that happened to a top-up order. */
class TopupOrderEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'type', 'payload', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }
}
