<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * NAARA-PROMPT-22 §3.5 — sent to every super_admin the moment the admin panel
 * path changes, carrying the new path in plain text. The paper trail against
 * exactly the failure mode that matters here: the acting admin's own session
 * drops right after the change and they have no other record of what they
 * just set it to.
 */
class AdminPathChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $newPath) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('['.config('app.name').'] Admin panel path changed')
            ->greeting('The admin panel URL just changed.')
            ->line('New admin path: /'.$this->newPath)
            ->line('If you did not make this change, contact another super_admin immediately.');
    }
}
