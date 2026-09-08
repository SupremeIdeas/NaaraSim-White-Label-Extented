<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StatusSubscriber extends Model
{
    protected $fillable = ['email', 'webhook_url', 'token', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (StatusSubscriber $s) => $s->token ??= Str::random(48));
    }
}
