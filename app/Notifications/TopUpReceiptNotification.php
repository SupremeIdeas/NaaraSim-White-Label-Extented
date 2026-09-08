<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued wallet top-up receipt (transactional emails pass). Sent once
 * a verified payment credits the wallet.
 */
class TopUpReceiptNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(
        public float $amount,
        public string $currency,
        public string $gateway,
        public float $newBalance,
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
            'title' => 'Wallet topped up',
            'body' => strtoupper($this->currency).' '.number_format($this->amount, 2).' added via '.ucfirst($this->gateway).'.',
            'action_url' => url('/wallet'),
            'action_label' => 'Open wallet',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(\App\Support\MailTemplates::subject('top-up', 'Wallet topped up — '.config('app.name')))
            ->view('emails.top-up', [
                'name' => $notifiable->name ?? null,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'gateway' => ucfirst($this->gateway),
                'newBalance' => $this->newBalance,
                'url' => url('/wallet'),
            ]);
    }
}
