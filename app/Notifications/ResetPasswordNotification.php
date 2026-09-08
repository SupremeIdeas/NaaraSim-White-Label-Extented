<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * Branded, queued password-reset notification (Module 22). Builds the reset URL
 * exactly as Laravel does (token + email) and renders it through our brand
 * template.
 *
 * Uses Queueable (NOT InteractsWithQueue) — see VerifyEmailNotification for why:
 * a queued notification must expose $connection/$queue/$delay.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expires = Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(\App\Support\MailTemplates::subject('reset', 'Reset your password — '.config('app.name')))
            ->view('emails.reset', [
                'url' => $url,
                'expires' => $expires,
            ]);
    }
}
