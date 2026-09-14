<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * GDPR account-lifecycle notice (blueprint Section 26). Covers every state
 * AccountService moves an account through — self-deactivate, reactivate, a
 * deletion request, its cancellation, and the final erasure — none of which
 * previously told the user anything at all.
 */
class AccountLifecycleNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public const DEACTIVATED = 'deactivated';

    public const REACTIVATED = 'reactivated';

    public const DELETION_REQUESTED = 'deletion_requested';

    public const DELETION_CANCELLED = 'deletion_cancelled';

    public const ERASED = 'erased';

    public function __construct(public string $action)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        [$title, $body] = match ($this->action) {
            self::DEACTIVATED => ['Your account is deactivated', 'You can sign back in any time to reactivate it.'],
            self::REACTIVATED => ['Welcome back', 'Your account is active again.'],
            self::DELETION_REQUESTED => ['Deletion request received', 'A super admin will review this before anything is erased.'],
            self::DELETION_CANCELLED => ['Deletion request withdrawn', 'Your account stays exactly as it is.'],
            self::ERASED => ['Your account has been deleted', 'Your data has been permanently erased, as requested.'],
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
            self::DEACTIVATED => ['Your account is deactivated', 'Your account is deactivated'],
            self::REACTIVATED => ['Your account is active again', 'Welcome back'],
            self::DELETION_REQUESTED => ['We received your deletion request', 'Deletion request received'],
            self::DELETION_CANCELLED => ['Your deletion request was withdrawn', 'Deletion request withdrawn'],
            self::ERASED => ['Your account has been deleted', 'Your account has been deleted'],
        };

        return (new MailMessage)
            ->subject($subject.' — '.config('app.name'))
            ->view('emails.account-lifecycle', [
                'action' => $this->action,
                'name' => $notifiable->name ?? null,
                'heading' => $heading,
                'url' => url('/account'),
            ]);
    }
}
