<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a customer a human support agent has replied to their ticket (Module
 * 25). Branded + queued.
 */
class HumanRepliedNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'support',
            'icon' => 'id-card',
            'title' => 'Our team replied',
            'body' => 'A support agent has responded to your conversation.',
            'action_url' => url('/support'),
            'action_label' => 'Open chat',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A reply from our support team — '.config('app.name'))
            ->view('emails.human-replied', ['url' => url('/support')]);
    }
}
