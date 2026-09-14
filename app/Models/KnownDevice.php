<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A device (IP+user-agent fingerprint) a user has signed in from before. */
class KnownDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint',
        'ip_address',
        'user_agent',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function fingerprint(?string $ip, ?string $userAgent): string
    {
        return hash('sha256', ($ip ?? '').'|'.($userAgent ?? ''));
    }
}
