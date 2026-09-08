<?php

namespace App\Support;

use Illuminate\Notifications\Notification;

/**
 * Best-effort transactional-mail dispatcher. A money path must NEVER break
 * because of an email, so every send here is:
 *   - gated on MailSettings::isConfigured() (no pointless queued jobs, and no
 *     mail attempted before the operator has set up a mailer), and
 *   - wrapped so a mail failure can never bubble into the caller.
 * Notifications themselves implement ShouldQueue, so the actual send is queued.
 */
class Mailer
{
    public static function notify(object $notifiable, Notification $notification): void
    {
        if (! MailSettings::isConfigured()) {
            return;
        }

        try {
            $notifiable->notify($notification);
        } catch (\Throwable) {
            // Swallow — transactional mail is best-effort and must not affect
            // the money action that triggered it.
        }
    }
}
