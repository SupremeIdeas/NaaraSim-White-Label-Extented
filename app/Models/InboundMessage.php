<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An SMS received on a user's Naara Line (Numbers overhaul §1). Auth-scoped; the
 * raw provider is masked (money-safety rule 1.2). On create it bumps the
 * per-conversation MessageThread summary (unread++).
 */
class InboundMessage extends Model
{
    protected $fillable = [
        'user_id', 'virtual_number_id', 'from_number', 'body', 'attachment_url',
        'voicemail_path', 'voicemail_duration_seconds',
        'provider', 'provider_ref', 'read_at', 'received_at',
    ];

    protected $hidden = ['provider'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'received_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (InboundMessage $m) {
            MessageThread::record(
                userId: $m->user_id,
                counterpart: $m->from_number,
                body: (string) $m->body,
                direction: 'in',
                virtualNumberId: $m->virtual_number_id,
                at: $m->received_at ?? $m->created_at,
                incUnread: $m->read_at === null,
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function virtualNumber(): BelongsTo
    {
        return $this->belongsTo(VirtualNumber::class);
    }
}
