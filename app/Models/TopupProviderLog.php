<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Redacted request/response log of every provider call (for disputes). */
class TopupProviderLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider_code', 'endpoint', 'reference', 'request', 'response', 'http_status', 'duration_ms', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
