<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** In-app record that the email address was confirmed. Fired once, from the Verified event (a second click never reaches it). */
class EmailVerifiedNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return ['title' => 'Email confirmed', 'body' => 'Your email address is confirmed. Welcome aboard.', 'icon' => 'badge-check', 'url' => route('dashboard')];
    }
}
