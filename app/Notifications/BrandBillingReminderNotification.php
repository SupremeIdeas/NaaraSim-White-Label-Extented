<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Brand-listing past-due top-up reminder (BUILD-9 §5.2). Sent when a monthly
 * charge can't be covered — the listing pauses (not cancelled) and resumes
 * automatically on the next successful charge.
 */
class BrandBillingReminderNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(public string $brandName, public float $amountUsd, public string $planName)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'brand',
            'icon' => 'wallet',
            'title' => 'Top up to keep your listing live',
            'body' => $this->brandName.' is paused — add $'.number_format($this->amountUsd, 2).' to your wallet to resume.',
            'action_url' => url('/brand/manage'),
            'action_label' => 'Manage listing',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your brand listing is paused — top up to resume')
            ->greeting('Hi'.($notifiable->name ? ' '.$notifiable->name : '').',')
            ->line('We couldn’t renew your **'.$this->brandName.'** listing ('.$this->planName.' plan) — your wallet was short of $'.number_format($this->amountUsd, 2).'.')
            ->line('Your listing is paused (not cancelled) and hidden from the directory. Top up and it resumes automatically on the next daily run — no re-approval needed.')
            ->action('Top up & manage', url('/brand/manage'))
            ->line('Follows and credits already earned are untouched.');
    }
}
