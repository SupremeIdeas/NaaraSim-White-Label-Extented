<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when someone invites this user to their shared wallet plan (Prompt 11
 * §3) — without this, a pending invite would sit invisibly in the database
 * with no way for the invitee to ever know to accept it.
 */
class WalletGroupInvitedNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(public string $ownerName)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'wallet',
            'icon' => 'users',
            'title' => 'Shared plan invite',
            'body' => $this->ownerName.' invited you to spend from their shared plan.',
            'action_url' => url('/wallet'),
            'action_label' => 'View invite',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You\'ve been invited to a shared plan — '.config('app.name'))
            ->view('emails.wallet-group-invited', [
                'name' => $notifiable->name ?? null,
                'ownerName' => $this->ownerName,
                'url' => url('/wallet'),
            ]);
    }
}
