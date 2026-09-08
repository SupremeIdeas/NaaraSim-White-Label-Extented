<?php

namespace App\Services\Push;

use App\Models\PushSubscription as PushSubscriptionModel;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Production web-push sender (owner request). Signs with our own VAPID keys and
 * encrypts the payload per RFC 8291 via minishlink/web-push, then posts to the
 * browser's push service. No third-party push service — the endpoint is the
 * browser vendor's own, and the keys are ours.
 */
class MinishlinkPushSender implements WebPushSender
{
    public function send(PushSubscriptionModel $subscription, array $payload): bool
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ]);

        $report = $webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
            ]),
            json_encode($payload),
        );

        // A subscription the browser has revoked reports as expired/gone — the
        // caller prunes it so we stop trying.
        return ! $report->isSubscriptionExpired();
    }
}
