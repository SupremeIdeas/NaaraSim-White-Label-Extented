<?php

namespace App\Notifications;

use App\Models\WhiteLabelProjectIntake;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prompt 21-EXT2 §6 — tells a merchant their white-label deployment timeline
 * has completed. Mirrors MerchantSubscriptionDueNotification's shape (plain
 * fluent MailMessage, no custom view needed for something this simple).
 */
class WhiteLabelDeploymentReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WhiteLabelProjectIntake $intake) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('['.config('app.name').'] Your white-label platform is ready')
            ->greeting('Great news, '.$this->intake->desired_brand_name.' is live!')
            ->line('Your white-label deployment has completed.')
            ->line('Our team will be in touch on WhatsApp ('.$this->intake->whatsapp_number.') with next steps.')
            ->action('Open your dashboard', route('merchant.white-label'));
    }
}
