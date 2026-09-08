<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A high-severity operational alert delivered to an admin by email (BUILD-5 §3).
 * The durable sink is still the error_logs row AlertAdminJob writes; this is the
 * near-real-time channel so an admin actually SEES a critical alert (a provider
 * down, a payout failure) promptly rather than hours later in a log.
 */
class AdminAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $context */
    public function __construct(
        public string $code,
        public string $alertMessage,
        public array $context = [],
        public string $severity = 'critical',
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('['.config('app.name').'] '.strtoupper($this->severity).' — '.$this->code)
            ->greeting(ucfirst($this->severity).' platform alert')
            ->line($this->alertMessage);

        foreach ($this->context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $mail->line('• '.$key.': '.($value ?? '—'));
            }
        }

        return $mail->action('Open admin dashboard', route('admin.dashboard'));
    }
}
