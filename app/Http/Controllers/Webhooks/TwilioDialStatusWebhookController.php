<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\VoiceCall;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use App\Services\Voice\VoiceDialerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Settlement webhook for the in-browser dialer (Live Voice — Part B). Twilio
 * POSTs here when the dialed leg ends (the <Dial action> callback), carrying
 * DialCallStatus + DialCallDuration. We verify the signature (rule 9), then
 * settle: bill the minutes actually used and REFUND the unused hold. Settlement
 * is idempotent, so this and a client-side hang-up can both fire safely — the
 * wallet moves exactly once (money-safety rules 5–7).
 */
class TwilioDialStatusWebhookController extends Controller
{
    public function __invoke(Request $request, VoiceDialerService $dialer): Response
    {
        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        $verified = $voice->verifyWebhook($request, $request->fullUrl());

        WebhookLog::create([
            'provider' => 'twilio:dial-status',
            'event_type' => $request->input('DialCallStatus'),
            'payload' => $request->all(),
            'signature' => $request->header('X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => $verified,
        ]);

        if (! $verified) {
            return $this->emptyTwiml(403);
        }

        $call = VoiceCall::find((int) $request->query('call', 0));
        if ($call !== null) {
            $duration = (int) $request->input('DialCallDuration', 0);
            $status = $this->mapStatus((string) $request->input('DialCallStatus', ''));
            $dialer->settle($call, $duration, $status);
        }

        // Nothing more to say to the caller — end the parent call cleanly.
        return $this->emptyTwiml();
    }

    /** Map Twilio's DialCallStatus onto our internal settlement statuses. */
    private function mapStatus(string $dialStatus): string
    {
        return match ($dialStatus) {
            'completed', 'answered' => VoiceCall::STATUS_COMPLETED,
            'failed' => VoiceCall::STATUS_FAILED,
            default => VoiceCall::STATUS_NO_ANSWER, // no-answer|busy|canceled
        };
    }

    private function emptyTwiml(int $status = 200): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>', $status)
            ->header('Content-Type', 'text/xml');
    }
}
