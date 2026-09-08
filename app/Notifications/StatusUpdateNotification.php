<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Status-page incident notification to a subscriber (Status page §1). Queued so
 * posting an update never blocks the admin request.
 */
class StatusUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Incident $incident, public string $unsubscribeToken = '') {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $latest = $this->incident->updates()->first();

        $mail = (new MailMessage)
            ->subject('['.config('app.name').' Status] '.$this->incident->title)
            ->greeting($this->incident->isResolved() ? 'Resolved: '.$this->incident->title : $this->incident->title)
            ->line('Status: '.ucfirst($this->incident->status));

        if ($latest) {
            $mail->line($latest->body);
        }

        return $mail->action('View status page', route('status'));
    }
}
