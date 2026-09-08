<?php

namespace App\Events;

use App\Models\SmsOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when an OTP arrives, regardless of which provider served it, so
 * the dashboard shows the code identically (blueprint Sections 11.3 & 12.2).
 * Delivered on the user's private channel; the code is user-facing, cost is not.
 */
class OtpReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $smsOrderId,
        public string $code,
        public string $number,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'sms_order_id' => $this->smsOrderId,
            'code' => $this->code,
            'number' => $this->number,
        ];
    }
}
