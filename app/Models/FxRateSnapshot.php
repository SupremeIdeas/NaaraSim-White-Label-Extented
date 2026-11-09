<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FxRateSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['source', 'base', 'rates_json', 'fetched_at'];

    protected function casts(): array
    {
        return ['rates_json' => 'array', 'fetched_at' => 'datetime'];
    }
}
