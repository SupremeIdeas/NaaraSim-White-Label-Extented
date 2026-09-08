<?php

namespace App\Services\Push;

use App\Models\PushSubscription;

/**
 * Sends one encrypted web-push message to one browser subscription. Behind an
 * interface so the real VAPID sender is swapped for a fake in tests (no network,
 * no crypto). Returns whether the subscription is still valid — a false result
 * means the browser has unsubscribed (410/404) and the row should be pruned.
 */
interface WebPushSender
{
    /**
     * @param  array<string, mixed>  $payload  {title, body, url, icon}
     * @return bool  true = delivered/accepted; false = gone, prune it
     */
    public function send(PushSubscription $subscription, array $payload): bool;
}
