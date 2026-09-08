<?php

namespace App\Jobs;

use App\Models\CallEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Record an inbound-call event on a forwarded number (Live Voice — Part A).
 * Runs on the queue so the voice webhook returns TwiML immediately (money-safety
 * rule 8 — external-API side effects are queued, never synchronous).
 *
 * @param array{user_id:?int, rule_id:?int, call_sid:?string, from:?string, to:?string, status:string} $data
 */
class LogCallEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string,mixed> $data */
    public function __construct(public array $data)
    {
    }

    public function handle(): void
    {
        CallEvent::create([
            'user_id' => $this->data['user_id'] ?? null,
            'call_forwarding_rule_id' => $this->data['rule_id'] ?? null,
            'call_sid' => $this->data['call_sid'] ?? null,
            'from_number' => $this->data['from'] ?? null,
            'to_number' => $this->data['to'] ?? null,
            'status' => $this->data['status'] ?? 'ringing',
        ]);
    }
}
