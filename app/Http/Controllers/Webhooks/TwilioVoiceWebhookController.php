<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\LogCallEventJob;
use App\Models\CallForwardingRule;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Inbound-call webhook for forwarded permanent numbers (Live Voice — Part A).
 * Twilio POSTs here when a call reaches a NaaraSim number; we verify the request
 * signature (money-safety rule 9) BEFORE trusting it, resolve the forwarding
 * rule for the called number, and return TwiML that dials the user's target
 * (with a fallback on no-answer). Call activity is logged in a queued job so the
 * response stays instant (rule 8). No active rule → a polite reject.
 */
class TwilioVoiceWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        $verified = $voice->verifyWebhook($request, $request->url());

        $to = (string) $request->input('To', '');
        $from = (string) $request->input('From', '');

        WebhookLog::create([
            'provider' => 'twilio:voice',
            'event_type' => $request->input('CallStatus'),
            'payload' => $request->all(),
            'signature' => $request->header('X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return $this->twiml('<Reject/>', 403);
        }

        $rule = CallForwardingRule::query()
            ->where('twilio_number', $to)
            ->where('status', CallForwardingRule::ACTIVE)
            ->first();

        if ($rule === null) {
            // Nobody's forwarding this number — reject gracefully.
            return $this->twiml('<Response><Reject reason="rejected"/></Response>');
        }

        LogCallEventJob::dispatch([
            'user_id' => $rule->user_id, 'rule_id' => $rule->id, 'call_sid' => $request->input('CallSid'),
            'from' => $from, 'to' => $to, 'status' => 'ringing',
        ]);

        // Preserve the original caller's number as caller ID so the user knows
        // who's actually calling. Voicemail (Prompt 11): if neither the primary
        // nor the fallback number answers, TwiML falls through to a recording.
        return $this->twiml($voice->forwardTwiml(
            $rule->forward_to_number,
            $from ?: null,
            $rule->fallback_number,
            route('webhooks.twilio.recording'),
        ));
    }

    private function twiml(string $xml, int $status = 200): Response
    {
        if (! str_starts_with($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'.(str_starts_with($xml, '<Response') ? $xml : '<Response>'.$xml.'</Response>');
        }

        return response($xml, $status)->header('Content-Type', 'text/xml');
    }
}
