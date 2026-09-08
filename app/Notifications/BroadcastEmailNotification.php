<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One recipient's copy of an admin email broadcast (Email Studio §3.2). Queued,
 * so a blast never runs synchronously in the request cycle (money rule 8 spirit).
 * Body is admin-authored HTML, already sanitized before the broadcast was stored.
 */
class BroadcastEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectLine)
            ->view('emails.broadcast', [
                'subject' => $this->subjectLine,
                'bodyHtml' => $this->bodyHtml,
            ]);
    }
}
