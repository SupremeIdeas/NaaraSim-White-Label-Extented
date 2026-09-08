<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'code',
        'message',
        'context',
        'severity',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }
}
