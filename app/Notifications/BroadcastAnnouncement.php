<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Concerns\InApp;
use Illuminate\Notifications\Notification;

/**
 * One recipient's copy of an admin announcement / offer (owner request).
 * In-app only (the bell) — an offer blast is not an email, and at platform scale
 * the fan-out is done by BroadcastAnnouncementJob writing these directly in
 * chunks. Deliberately NOT ShouldQueue: the enclosing job is already queued and
 * writes the database rows synchronously in bulk.
 */
class BroadcastAnnouncement extends Notification
{
    use InApp;

    public function __construct(private Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        return $this->announcement->toInApp();
    }
}
