<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A guest contact-form message (Module 27), delivered to the operator's
 * support inbox as a branded, queued email. On-demand routed (no User row).
 */
class ContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $email,
        public string $subject,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Contact form: '.$this->subject.' — '.config('app.name'))
            ->replyTo($this->email, $this->name)
            ->view('emails.contact-message', [
                'name' => $this->name,
                'email' => $this->email,
                'topic' => $this->subject,
                'body' => $this->body,
            ]);
    }
}
