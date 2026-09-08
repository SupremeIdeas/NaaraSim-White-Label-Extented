<?php

namespace App\Notifications;

use App\Services\Pricing\CurrencyService;
use App\Services\Pricing\TaxService;
use App\Support\LocaleCurrency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Itemised purchase receipt (BUILD-7 §1), email + in-app. Fired on a completed
 * order (eSIM / number / permanent line + its renewal). Shows the item (the
 * Model name, never the internal provider), the amount in the currency the user
 * was shown (via CurrencyService — never raw USD if they view in NGN/etc.), the
 * date and a reference. A tax line appears only where an admin has configured a
 * rate for the buyer's country. The wizard convenience fee has its own separate
 * wallet entry, so it shows as its own line in the receipts history.
 */
class PurchaseReceiptNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(
        public string $item,
        public float $usdAmount,
        public string $reference,
        public ?string $country = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'purchase',
            'icon' => 'file-text',
            'title' => 'Receipt: '.$this->item,
            'body' => $this->displayAmount($notifiable).' · Ref '.$this->reference,
            'action_url' => url('/receipts'),
            'action_label' => 'View receipts',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = LocaleCurrency::resolve($notifiable);
        $fx = app(CurrencyService::class);
        $tax = app(TaxService::class)->taxFor($this->country, $this->usdAmount);

        $mail = (new MailMessage)
            ->subject('Your receipt — '.config('app.name'))
            ->greeting('Thanks for your purchase'.($notifiable->name ? ', '.$notifiable->name : '').'!')
            ->line('**'.$this->item.'**')
            ->line('Amount: '.$fx->format($this->usdAmount, $currency));

        if ($tax > 0) {
            $mail->line('Tax: '.$fx->format($tax, $currency));
            $mail->line('Total: '.$fx->format($this->usdAmount + $tax, $currency));
        }

        return $mail
            ->line('Reference: '.$this->reference)
            ->line('Date: '.now()->toDayDateTimeString())
            ->action('View all receipts', url('/receipts'))
            ->line('This is a receipt for your records, not a tax invoice.');
    }

    private function displayAmount(object $notifiable): string
    {
        return app(CurrencyService::class)->format($this->usdAmount, LocaleCurrency::resolve($notifiable));
    }
}
