<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued refund notice (transactional emails pass). Sent when money is
 * returned to the wallet — a failed/unfulfilled order, an OTP that never
 * arrived, or an orphan-charge auto-refund. Reassures the user their money is
 * safe and back in their wallet.
 */
class RefundNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(
        public float $amount,
        public string $currency,
        public ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'wallet',
            'icon' => 'wallet',
            'title' => 'Refunded to your wallet',
            'body' => strtoupper($this->currency).' '.number_format($this->amount, 2).' is back in your wallet'.($this->reason ? ' — '.$this->reason : '.').'',
            'action_url' => url('/wallet'),
            'action_label' => 'Open wallet',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(\App\Support\MailTemplates::subject('refund', 'Refunded to your wallet — '.config('app.name')))
            ->view('emails.refund', [
                'name' => $notifiable->name ?? null,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'reason' => $this->reason,
                'url' => url('/wallet'),
            ]);
    }
}
