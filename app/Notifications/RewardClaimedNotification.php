<?php

namespace App\Notifications;

use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** In-app bell entry for a reward the member just claimed (Engineering v3, Phase 3.3 / Issue 7: every visible state change also notifies). */
class RewardClaimedNotification extends Notification implements ShouldQueue
{
    use InApp;
    use Queueable;

    public function __construct(public float $credits, public string $reason = 'Daily check-in') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'offer',
            'icon' => 'star',
            'title' => 'Reward claimed: '.$this->reason,
            'body' => '+'.rtrim(rtrim(number_format($this->credits, 2), '0'), '.').' NaaraCredits added to your balance.',
            'action_url' => url('/wallet'),
            'action_label' => 'View wallet',
        ];
    }
}
