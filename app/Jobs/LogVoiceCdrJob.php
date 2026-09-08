<?php

namespace App\Jobs;

use App\Models\CallEvent;
use App\Models\VoiceCall;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Record a completed outbound dialer call as an operator call-event (Live Voice
 * — Part B). Runs on the queue so settlement never blocks on logging (money-
 * safety rule 8 — external/side-effect writes are queued). The VoiceCall row is
 * the authoritative billing record; this is the lightweight activity log that
 * sits alongside Part A's inbound events.
 */
class LogVoiceCdrJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $voiceCallId)
    {
    }

    public function handle(): void
    {
        $call = VoiceCall::find($this->voiceCallId);
        if ($call === null) {
            return;
        }

        CallEvent::create([
            'user_id' => $call->user_id,
            'call_forwarding_rule_id' => null, // outbound dialer, not a forwarding rule
            'call_sid' => $call->call_sid,
            'from_number' => null, // outbound leg originates from NaaraSim's caller ID
            'to_number' => $call->destination,
            'status' => $call->status,
        ]);
    }
}
