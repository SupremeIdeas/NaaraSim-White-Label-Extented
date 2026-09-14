<?php

namespace App\Notifications;

use App\Models\KycVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a KYC verification reaches a final decision (approved/rejected/
 * failed) — via manual admin review, a synchronous provider decision at
 * submission, or an async provider webhook. Previously the user only found
 * out by revisiting the verification screen (a pull, never a push).
 */
class KycResultNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(
        public string $status,
        public int $level,
        public ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        $approved = $this->status === KycVerification::APPROVED;

        return [
            'category' => 'account',
            'icon' => $approved ? 'shield-check' : 'shield-alert',
            'title' => $approved ? 'Identity verified' : 'Identity verification unsuccessful',
            'body' => $approved
                ? 'Your identity has been verified at level '.$this->level.'.'
                : 'Your identity verification could not be completed'.($this->reason ? ' — '.$this->reason : '.').'',
            'action_url' => url('/account/verification'),
            'action_label' => 'View verification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->status === KycVerification::APPROVED;

        return (new MailMessage)
            ->subject(($approved ? 'You\'re verified' : 'Verification unsuccessful').' — '.config('app.name'))
            ->view('emails.kyc-result', [
                'name' => $notifiable->name ?? null,
                'approved' => $approved,
                'level' => $this->level,
                'reason' => $this->reason,
                'url' => url('/account/verification'),
            ]);
    }
}
