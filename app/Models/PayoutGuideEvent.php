<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutGuideEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'country', 'rail', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
