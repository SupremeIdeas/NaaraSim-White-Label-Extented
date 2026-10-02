<?php

namespace App\Notifications;

use App\Models\EarningsTransfer;
use App\Services\Payouts\Peer\EarningsTransferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a member about a transfer that needs them (requested) or that changed (accepted / declined / expired). */
class EarningsTransferNotice extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(public int $transferId, public string $kind) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function parts(): array
    {
        $t = EarningsTransfer::with(['sender:id,name', 'recipient:id,name'])->find($this->transferId);
        $amount = $t ? '$'.number_format((float) $t->amount_usd, 2) : '';
        $other = $t ? EarningsTransferService::maskedName($this->kind === 'requested' ? $t->sender : $t->recipient) : '';

        return [
            'title' => __('payouts.peer.notice.'.$this->kind.'.title', ['amount' => $amount]),
            'body' => __('payouts.peer.notice.'.$this->kind.'.body', ['amount' => $amount, 'name' => $other, 'hours' => $t?->expires_at ? max(1, (int) now()->diffInHours($t->expires_at, false)) : 0]),
        ];
    }

    public function inApp(object $notifiable): array
    {
        $p = $this->parts();

        return [
            'category' => 'payout', 'icon' => $this->kind === 'accepted' ? 'check' : 'wallet',
            'title' => $p['title'], 'body' => $p['body'],
            'action_url' => url('/account/send-earnings'), 'action_label' => __('payouts.peer.open'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->parts();

        return (new MailMessage)->subject($p['title'].' — '.config('app.name'))
            ->greeting('Hi '.($notifiable->name ?? 'there').',')
            ->line($p['body'])
            ->action(__('payouts.peer.open'), url('/account/send-earnings'));
    }
}
