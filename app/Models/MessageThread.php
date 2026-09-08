<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A per-conversation summary (Numbers overhaul §1) — one row per (user,
 * counterpart), updated on every send/receive with the last message preview +
 * unread count, so the inbox list and the nav unread badge are cheap indexed
 * lookups rather than a live UNION across inbound + outbound on every load.
 */
class MessageThread extends Model
{
    protected $fillable = [
        'user_id', 'virtual_number_id', 'counterpart_number',
        'last_body', 'last_direction', 'last_at', 'unread_count',
    ];

    protected function casts(): array
    {
        return ['last_at' => 'datetime', 'unread_count' => 'integer'];
    }

    /** Upsert the summary for a message. Inbound bumps unread; outbound clears it. */
    public static function record(
        int $userId,
        string $counterpart,
        string $body,
        string $direction,
        ?int $virtualNumberId,
        ?Carbon $at = null,
        bool $incUnread = false,
    ): self {
        $thread = static::firstOrNew(['user_id' => $userId, 'counterpart_number' => $counterpart]);
        $thread->virtual_number_id = $virtualNumberId ?? $thread->virtual_number_id;
        $thread->last_body = mb_substr($body, 0, 480);
        $thread->last_direction = $direction;
        $thread->last_at = $at ?? now();
        if ($direction === 'out') {
            $thread->unread_count = 0; // replying clears unread
        } elseif ($incUnread) {
            $thread->unread_count = ($thread->unread_count ?? 0) + 1;
        }
        $thread->save();

        return $thread;
    }

    public static function totalUnread(int $userId): int
    {
        return (int) static::where('user_id', $userId)->sum('unread_count');
    }

    public function markRead(): void
    {
        if ($this->unread_count > 0) {
            $this->update(['unread_count' => 0]);
        }
        InboundMessage::where('user_id', $this->user_id)
            ->where('from_number', $this->counterpart_number)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
