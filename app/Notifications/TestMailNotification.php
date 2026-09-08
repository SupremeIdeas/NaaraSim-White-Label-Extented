<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admin "send test email" (Module 22). Intentionally NOT queued — it is sent
 * synchronously (Notification::sendNow) so any SMTP/auth error surfaces to the
 * admin right away instead of disappearing into a failed job.
 */
class TestMailNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test email — '.config('app.name'))
            ->view('emails.test', []);
    }
}
