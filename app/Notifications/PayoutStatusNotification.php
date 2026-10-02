<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use App\Support\PayoutStatusText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a payee when their withdrawal changes state in a way that matters: held for
 * a check, delayed, delivered, or returned. The wording is the single user-facing
 * mapping in PayoutStatusText (translated); it never carries a rule name or score.
 */
class PayoutStatusNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(public int $payoutRequestId) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function request(): ?PayoutRequest
    {
        return PayoutRequest::find($this->payoutRequestId);
    }

    private function line(): string
    {
        $r = $this->request();

        return $r ? PayoutStatusText::text($r) : '';
    }

    public function inApp(object $notifiable): array
    {
        $r = $this->request();
        $tone = $r ? PayoutStatusText::tone($r) : 'neutral';

        return [
            'category' => 'payout',
            'icon' => $tone === 'success' ? 'check' : ($tone === 'danger' ? 'alert-triangle' : 'clock'),
            'title' => $r ? number_format((float) $r->amount, 2).' '.$r->currency.' withdrawal' : 'Withdrawal update',
            'body' => $this->line(),
            'action_url' => url('/rewards/withdraw'),
            'action_label' => 'View withdrawals',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your withdrawal update — '.config('app.name'))
            ->greeting('Hi '.($notifiable->name ?? 'there').',')
            ->line($this->line())
            ->action('View withdrawals', url('/rewards/withdraw'));
    }
}
