<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One recorded cookie choice (proof of consent). */
class CookieConsentLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['consent_id', 'user_id', 'policy_version', 'action', 'functional', 'analytics', 'marketing', 'ip_hash', 'user_agent', 'path', 'created_at'];

    protected function casts(): array
    {
        return ['functional' => 'boolean', 'analytics' => 'boolean', 'marketing' => 'boolean', 'created_at' => 'datetime'];
    }
}
