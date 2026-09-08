<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Services\Push\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes one payload to every browser subscription a user has (owner request).
 * Queued (blueprint rule 8 — external calls never run in the request cycle) and
 * self-pruning: a subscription the browser has revoked is deleted so we stop
 * paying to retry it. Best-effort — a push failure never affects anything else.
 */
class SendWebPushJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload  {title, body, url, icon}
     */
    public function __construct(
        public int $userId,
        public array $payload,
    ) {}

    public function handle(WebPushSender $sender): void
    {
        $subscriptions = PushSubscription::where('user_id', $this->userId)->get();

        foreach ($subscriptions as $subscription) {
            try {
                $stillValid = $sender->send($subscription, $this->payload);
                if (! $stillValid) {
                    $subscription->delete(); // browser unsubscribed — prune it
                } else {
                    $subscription->forceFill(['last_used_at' => now()])->saveQuietly();
                }
            } catch (\Throwable) {
                // best-effort; a bad endpoint must not fail the whole batch
            }
        }
    }
}
