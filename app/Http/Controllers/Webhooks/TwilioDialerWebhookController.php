<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VoiceTokenController;
use App\Models\VoiceCall;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Outbound-call TwiML for the in-browser dialer (Live Voice — Part B). Twilio's
 * TwiML Application POSTs here the moment the browser Device places a call; we
 * verify the request signature (money-safety rule 9) BEFORE trusting it, resolve
 * the pre-authorised VoiceCall (opened by VoiceDialerService::begin, which
 * already held the funded minutes), and return <Dial> to the destination.
 *
 * The funded block becomes <Dial timeLimit> — a HARD server-side hangup Twilio
 * enforces, so the call physically cannot run past what the wallet hold covers,
 * even if the browser stops counting down (rule 6, orphan-charge guard). The
 * Dial `action` points back at the settlement webhook, which bills the minutes
 * actually used and refunds the rest.
 */
class TwilioDialerWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        $verified = $voice->verifyWebhook($request, $request->fullUrl());

        WebhookLog::create([
            'provider' => 'twilio:dial',
            'event_type' => $request->input('CallStatus'),
            'payload' => $request->all(),
            'signature' => $request->header('X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return $this->twiml('<Response><Reject/></Response>', 403);
        }

        $callId = (int) $request->input('CallId', 0);
        $to = (string) $request->input('To', '');

        // Resolve the pre-authorised call and confirm it belongs to the client
        // identity Twilio is calling from — never dial off an unauthorised leg.
        $identity = $this->identityFrom($request);
        $userId = $identity !== null ? VoiceTokenController::userIdFrom($identity) : null;

        $call = VoiceCall::query()
            ->when($callId > 0, fn ($q) => $q->whereKey($callId))
            ->where('user_id', $userId)
            ->where('status', VoiceCall::STATUS_CONNECTING)
            ->latest('id')
            ->first();

        if ($call === null || $userId === null) {
            return $this->twiml('<Response><Reject/></Response>');
        }

        // Bill the funded call against the real destination stored at begin() —
        // never a destination smuggled in via the webhook params.
        $to = $call->destination;

        $call->update([
            'status' => VoiceCall::STATUS_IN_PROGRESS,
            'call_sid' => $request->input('CallSid'),
        ]);

        $callerId = (string) config('services.twilio.caller_id');
        $timeLimit = max(60, $call->minutes_authorized * 60);
        $action = route('webhooks.twilio.dial-status').'?call='.$call->id;

        $dial = '<Dial timeLimit="'.$timeLimit.'" answerOnBridge="true" action="'.htmlspecialchars($action, ENT_QUOTES).'" method="POST"'
            .($callerId !== '' ? ' callerId="'.htmlspecialchars($callerId, ENT_QUOTES).'"' : '')
            .'><Number>'.htmlspecialchars($to, ENT_QUOTES).'</Number></Dial>';

        return $this->twiml('<Response>'.$dial.'</Response>');
    }

    /** Twilio sends the caller as `client:<identity>` in the From/Caller field. */
    private function identityFrom(Request $request): ?string
    {
        foreach (['From', 'Caller'] as $field) {
            $val = (string) $request->input($field, '');
            if (str_starts_with($val, 'client:')) {
                return substr($val, strlen('client:'));
            }
        }

        return null;
    }

    private function twiml(string $xml, int $status = 200): Response
    {
        if (! str_starts_with($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'.$xml;
        }

        return response($xml, $status)->header('Content-Type', 'text/xml');
    }
}
