<?php

namespace App\Notifications;

use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** In-app bell notification for a newly-unlocked Journey goal (best-effort, never blocks the credit grant). */
class JourneyGoalUnlockedNotification extends Notification implements ShouldQueue
{
    use InApp;
    use Queueable;

    public function __construct(public string $goalTitle, public float $credits) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'offer',
            'icon' => 'star',
            'title' => 'Goal reached: '.$this->goalTitle,
            'body' => '+'.number_format($this->credits, 0).' NaaraCredits added to your balance.',
            'action_url' => url('/journey?tab=goals'),
            'action_label' => 'View My Journey',
        ];
    }
}
