<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Journey\JourneyGoalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched right after an action that could complete a Journey goal
 * (a purchase, a check-in, a merchant adding a client, an invoice getting
 * paid) so the NaaraCredits reward feels instant. Journey.php also evaluates
 * lazily on page load as a guaranteed-to-catch-up fallback — this job is the
 * "feels instant" path, not the only path, so a lost/failed job never means a
 * lost reward.
 */
class EvaluateJourneyGoalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId) {}

    public function handle(JourneyGoalService $goals): void
    {
        $user = User::find($this->userId);
        if ($user !== null) {
            $goals->evaluate($user);
        }
    }
}
