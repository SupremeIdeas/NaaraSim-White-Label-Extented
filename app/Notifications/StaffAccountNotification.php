<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a staff member when an admin changes THEIR OWN access (blueprint
 * Section 27) — granted, scopes changed, revoked, or removed entirely. None
 * of this reached the affected person before; only an audit-log row existed.
 * Never carries a password or any other credential.
 */
class StaffAccountNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public const GRANTED = 'granted';

    public const SCOPES_UPDATED = 'scopes_updated';

    public const REVOKED = 'revoked';

    public const REMOVED = 'removed';

    /** @param  list<string>  $scopes */
    public function __construct(public string $action, public array $scopes = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        [$title, $body] = match ($this->action) {
            self::GRANTED => ['You now have staff access', 'An admin has given you staff access to '.config('app.name').'.'],
            self::SCOPES_UPDATED => ['Your staff access was updated', 'An admin changed what you can access.'],
            self::REVOKED => ['Your staff access was revoked', 'Your staff permissions have been removed — your regular account still works.'],
            self::REMOVED => ['Your staff account was removed', 'Your staff account has been permanently removed.'],
        };

        return [
            'category' => 'account',
            'icon' => 'shield-check',
            'title' => $title,
            'body' => $body,
            'action_url' => url('/account'),
            'action_label' => 'Account settings',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $heading] = match ($this->action) {
            self::GRANTED => ['You\'ve been given staff access', 'You now have staff access'],
            self::SCOPES_UPDATED => ['Your staff access was updated', 'Your staff access was updated'],
            self::REVOKED => ['Your staff access was revoked', 'Your staff access was revoked'],
            self::REMOVED => ['Your staff account was removed', 'Your staff account was removed'],
        };

        return (new MailMessage)
            ->subject($subject.' — '.config('app.name'))
            ->view('emails.staff-account', [
                'action' => $this->action,
                'name' => $notifiable->name ?? null,
                'heading' => $heading,
                'scopes' => $this->scopes,
                'url' => url('/account'),
            ]);
    }
}
