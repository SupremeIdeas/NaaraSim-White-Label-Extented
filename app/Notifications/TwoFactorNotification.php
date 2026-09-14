<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security alert for a two-factor authentication state change — same tone and
 * plumbing as PasswordChangedNotification, the closest existing analog.
 * Enabling/disabling 2FA never notified the user before this (Sept-14 owner
 * request); disabling in particular is worth flagging since it's exactly the
 * kind of change an attacker with a stolen session would make.
 */
class TwoFactorNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(public bool $enabled)
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
            'title' => $this->enabled ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled',
            'body' => $this->enabled
                ? 'Your account now requires a code from your authenticator app to sign in.'
                : 'If this wasn\'t you, secure your account immediately.',
            'action_url' => url('/account/security'),
            'action_label' => 'Review security',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Two-factor authentication '.($this->enabled ? 'enabled' : 'disabled').' — '.config('app.name'))
            ->view('emails.two-factor-changed', [
                'enabled' => $this->enabled,
                'when' => now()->format('j M Y, H:i').' UTC',
            ]);
    }
}
