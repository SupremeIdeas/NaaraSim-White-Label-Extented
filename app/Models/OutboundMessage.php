<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An SMS sent from a user's Naara Line (Numbers V6 §6). Auth-scoped; the raw
 * provider is masked (money-safety rule 1.2) and provider cost is never stored
 * — only the retail amount the user actually paid.
 */
class OutboundMessage extends Model
{
    protected $fillable = [
        'user_id',
        'virtual_number_id',
        'to_number',
        'body',
        'attachment_url',
        'provider',
        'provider_ref',
        'status',
        'segments',
        'amount_charged',
        'reference',
    ];

    /** Supplier masking: the raw provider is never serialised to the client. */
    protected $hidden = ['provider'];

    protected static function booted(): void
    {
        // Keep the conversation summary current (Numbers overhaul §1): a reply
        // updates the thread preview and clears its unread count.
        static::created(function (OutboundMessage $m) {
            MessageThread::record(
                userId: $m->user_id,
                counterpart: $m->to_number,
                body: (string) $m->body,
                direction: 'out',
                virtualNumberId: $m->virtual_number_id,
                at: $m->created_at,
            );
        });
    }

    protected function casts(): array
    {
        return [
            'segments' => 'integer',
            'amount_charged' => 'decimal:4',
        ];
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
