<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a user signs in from a device (IP+user-agent pair) never seen for
 * their account before (Sept-14 owner request) — the standard "new sign-in"
 * pattern most SaaS products use, via App\Listeners\RecordLoginDevice.
 */
class NewDeviceLoginNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
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
            'title' => 'New sign-in to your account',
            'body' => 'A new sign-in was detected from '.($this->ipAddress ?: 'an unrecognised device').'.',
            'action_url' => url('/account/security'),
            'action_label' => 'Review security',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New sign-in to your account — '.config('app.name'))
            ->view('emails.new-device-login', [
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'when' => now()->format('j M Y, H:i').' UTC',
                'url' => url('/account/security'),
            ]);
    }
}
