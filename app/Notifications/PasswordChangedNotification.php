<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued security alert sent when a user's password changes (Module
 * 22) — a standard account-security signal so a user notices an unexpected
 * change.
 */
class PasswordChangedNotification extends Notification implements ShouldQueue
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
            'category' => 'security',
            'icon' => 'shield-check',
            'title' => 'Your password was changed',
            'body' => 'If this wasn’t you, secure your account immediately.',
            'action_url' => url('/account/security'),
            'action_label' => 'Review security',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed — '.config('app.name'))
            ->view('emails.password-changed', [
                'when' => now()->format('j M Y, H:i').' UTC',
            ]);
    }
}
