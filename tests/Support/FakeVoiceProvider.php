<?php

namespace Tests\Support;

use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;

/**
 * Voice provider double for the in-browser dialer money tests (Live Voice —
 * Part B). Lets a test pin the wholesale per-minute rate so the PricingEngine
 * math + wallet hold/settle are deterministic without any real Twilio call.
 */
class FakeVoiceProvider implements VoiceProviderInterface
{
    public function __construct(public float $rate = 0.10)
    {
    }

    public function available(): bool
    {
        return true;
    }

    public function voiceRate(string $destination): float
    {
        return $this->rate;
    }

    public function accessToken(string $identity, int $ttl = 3600): string
    {
        return 'fake.token.'.$identity;
    }

    public function attachVoiceWebhook(string $numberSid, string $voiceUrl): void {}

    public function detachVoiceWebhook(string $numberSid): void {}

    public function verifyWebhook(Request $request, string $url): bool
    {
        return true;
    }

    public function forwardTwiml(string $to, ?string $callerId = null, ?string $fallback = null, ?string $voicemailActionUrl = null): string
    {
        return '<Response><Dial>'.$to.'</Dial></Response>';
    }

    public function bridgeCall(string $from, string $to, string $twimlUrl): string
    {
        return 'CA_fake';
    }
}
