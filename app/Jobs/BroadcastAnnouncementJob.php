<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\BroadcastAnnouncement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Fans an admin announcement out to every active user's notification bell
 * (owner request). Queued (blueprint rule 8 — bulk work never runs in the
 * request cycle) and memory-safe at platform scale: users are streamed in
 * chunks by id and the database notifications are written in bulk per chunk,
 * so one blast never spawns a million queue jobs.
 */
class BroadcastAnnouncementJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $announcementId) {}

    public function handle(): void
    {
        $announcement = Announcement::find($this->announcementId);
        if ($announcement === null || $announcement->status === 'sent') {
            return; // deleted, or already fanned out (idempotent)
        }

        $announcement->update(['status' => 'sending']);
        $notification = new BroadcastAnnouncement($announcement);
        $count = 0;

        User::query()
            ->where('is_active', true)
            ->select(['id'])
            ->chunkById(1000, function ($users) use ($notification, &$count) {
                Notification::send($users, $notification);
                $count += $users->count();
            });

        $announcement->update([
            'status' => 'sent',
            'recipients' => $count,
            'sent_at' => now(),
        ]);
    }
}
