<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms to a reporter that their spam report helped get a number blocked
 * platform-wide. There is no reliable way to notify the OWNER of the blocked
 * number (SpamBlockedCaller stores only the raw msisdn, with no relation to a
 * NaaraSim account it might belong to) — this is reporter-side only.
 */
class SpamBlockReporterNotification extends Notification implements ShouldQueue
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
            'category' => 'security',
            'icon' => 'shield-check',
            'title' => 'Number blocked',
            'body' => $this->phoneNumber.' has been blocked platform-wide, thanks to your report.',
            'action_url' => url('/contacts'),
            'action_label' => 'View contacts',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A number you reported has been blocked — '.config('app.name'))
            ->view('emails.spam-blocked', [
                'name' => $notifiable->name ?? null,
                'phoneNumber' => $this->phoneNumber,
                'url' => url('/contacts'),
            ]);
    }
}
