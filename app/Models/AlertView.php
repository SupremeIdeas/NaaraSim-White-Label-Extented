<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertView extends Model
{
    protected $fillable = ['alert_id', 'user_id', 'views', 'dismissed_at'];

    protected function casts(): array
    {
        return ['dismissed_at' => 'datetime', 'views' => 'integer'];
    }
}
