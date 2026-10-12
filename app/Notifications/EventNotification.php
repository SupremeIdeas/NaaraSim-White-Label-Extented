<?php

namespace App\Notifications;

use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one notification behind every row of config/notification_events.php. Channels are decided by Notify (preferences, quiet hours, the
 * event's own channel list) and handed in, so this class never second-guesses them. Queued with backoff; a failure is retried, never thrown into the caller.
 */
class EventNotification extends Notification implements ShouldQueue
{
    use InApp;
    use Queueable;

    public int $tries = 3;

    /** @param list<string> $via laravel channel names: database, mail, webpush */
    public function __construct(public string $event, public array $payload, public array $via)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function via(object $notifiable): array
    {
        return $this->via;
    }

    public function inApp(object $notifiable): array
    {
        return $this->payload + ['category' => 'system', 'icon' => 'bell', 'title' => '', 'body' => ''];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject((string) $this->payload['title'].' - '.config('app.name'))->greeting('Hi '.($notifiable->name ?? 'there').',')->line((string) $this->payload['body']);
        if (! empty($this->payload['action_url'])) {
            $m->action((string) ($this->payload['action_label'] ?? 'Open'), (string) $this->payload['action_url']);
        }

        return $m;
    }
}
