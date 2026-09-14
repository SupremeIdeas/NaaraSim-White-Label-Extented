<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms a port-out (right-to-leave) request. The toast at the point of
 * request already promised "we'll email you" — this is the email that
 * previously never actually went out (Sept-14 owner request).
 */
class PortOutRequestedNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(public string $phoneNumber)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'numbers',
            'icon' => 'arrow-right-left',
            'title' => 'Port-out requested',
            'body' => 'We received your request to port '.$this->phoneNumber.' to another carrier.',
            'action_url' => url('/numbers/lines'),
            'action_label' => 'View my lines',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your port-out request — '.config('app.name'))
            ->view('emails.port-out-requested', [
                'name' => $notifiable->name ?? null,
                'phoneNumber' => $this->phoneNumber,
                'url' => url('/numbers/lines'),
            ]);
    }
}
