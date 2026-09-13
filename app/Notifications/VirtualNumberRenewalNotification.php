<?php

namespace App\Notifications;

use App\Models\VirtualNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prompt 10 — advance notice for a Naara Line's next billing date, sent once
 * per cycle (App\Console\Commands\RenewVirtualNumbersCommand resets the
 * marker on every actual renewal, so this fires again next month). States
 * the real amount and date either way: a renewing line gets a heads-up
 * before the charge; an opted-out line gets a heads-up before it ends.
 */
class VirtualNumberRenewalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VirtualNumber $line) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $date = $this->line->next_billing_date?->format('F j, Y');
        $amount = number_format((float) $this->line->monthly_retail, 2);

        if ($this->line->auto_renew) {
            return (new MailMessage)
                ->subject('['.config('app.name').'] Your Naara Line renews soon')
                ->greeting('Upcoming renewal')
                ->line("Your number {$this->line->phone_number} will renew on {$date} for \${$amount}, charged to your wallet.")
                ->line('No action needed if you want to keep it — just make sure your wallet balance covers it.')
                ->action('Manage this number', route('numbers.lines'));
        }

        return (new MailMessage)
            ->subject('['.config('app.name').'] Your Naara Line is ending')
            ->greeting('Line ending soon')
            ->line("You turned off auto-renew for {$this->line->phone_number} — it will end on {$date} and the number will be released.")
            ->line('Changed your mind? Turn auto-renew back on before then to keep it.')
            ->action('Manage this number', route('numbers.lines'));
    }
}
