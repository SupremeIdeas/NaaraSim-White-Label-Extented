<?php

namespace App\Notifications;

use App\Models\MerchantClientSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a merchant a client eSIM subscription is due / expired / low, so they
 * can collect from their client and renew (Merchant V2 client control). Queued.
 */
class MerchantSubscriptionDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MerchantClientSubscription $subscription, public string $reason = 'due') {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $client = $this->subscription->client;
        $left = $this->subscription->daysLeft();
        $line = match ($this->reason) {
            'expired' => "The eSIM for {$client?->name} has expired.",
            'renewed' => "The eSIM for {$client?->name} was auto-renewed.",
            'failed' => "Auto-renewal for {$client?->name} could not be provisioned — your funds are back in your wallet.",
            default => "The eSIM for {$client?->name} is due in {$left} day(s).",
        };

        return (new MailMessage)
            ->subject('['.config('app.name').'] Client eSIM '.$this->reason)
            ->greeting('Client subscription update')
            ->line($line)
            ->action('Manage clients', route('merchant.clients'));
    }
}
