<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded, queued email-verification notification (Module 22). Reuses Laravel's
 * signed verification URL (via the parent) but renders it through our brand
 * template instead of the framework default. Queued so it never blocks the
 * request (money rule 8 applies to all outbound calls).
 *
 * Uses Queueable (NOT InteractsWithQueue): a ShouldQueue notification must
 * expose $connection/$queue/$delay, which Queueable provides — InteractsWithQueue
 * does not, and its absence throws "Undefined property $connection" when the
 * notification is actually dispatched to a queue.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /** The public confirmation route, so the link also works from another device or browser (see EmailConfirmationController). */
    protected function verificationUrl($notifiable)
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'email.confirm',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(\App\Support\MailTemplates::subject('verify', 'Confirm your email — '.config('app.name')))
            ->view('emails.verify', [
                'url' => $url,
                'name' => $notifiable->name ?? null,
            ]);
    }
}
