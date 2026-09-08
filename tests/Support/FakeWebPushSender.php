<?php

namespace Tests\Support;

use App\Models\PushSubscription;
use App\Services\Push\WebPushSender;

/**
 * A no-network web-push sender for tests. Records what it was asked to send and
 * can be told a subscription is gone (to exercise the prune path).
 */
class FakeWebPushSender implements WebPushSender
{
    /** @var array<int, array{sub: PushSubscription, payload: array}> */
    public array $sent = [];

    public function __construct(public bool $stillValid = true) {}

    public function send(PushSubscription $subscription, array $payload): bool
    {
        $this->sent[] = ['sub' => $subscription, 'payload' => $payload];

        return $this->stillValid;
    }
}
