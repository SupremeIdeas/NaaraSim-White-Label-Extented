<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\RecordVoicemailJob;
use App\Models\CallForwardingRule;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Voicemail recording webhook (Prompt 11) — Twilio POSTs here when a
 * <Record> started by TwilioVoiceWebhookController finishes. Verified BEFORE
 * trusting it (money-safety rule 9), same as the voice webhook. Resolves the
 * owning user via the forwarding rule for the called number and queues the
 * download + storage (never synchronous — rule 8).
 */
class TwilioRecordingWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        $verified = $voice->verifyWebhook($request, $request->url());

        $to = (string) $request->input('To', '');
        $from = (string) $request->input('From', '');
        $recordingUrl = (string) $request->input('RecordingUrl', '');
        $recordingSid = (string) $request->input('RecordingSid', '');
        $duration = (int) $request->input('RecordingDuration', 0);

        WebhookLog::create([
            'provider' => 'twilio:recording',
            'event_type' => 'voicemail',
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

        if ($rule !== null && $recordingUrl !== '' && $recordingSid !== '') {
            RecordVoicemailJob::dispatch(
                userId: $rule->user_id,
                virtualNumberId: $rule->virtual_number_id,
                fromNumber: $from,
                toNumber: $to,
                recordingUrl: $recordingUrl,
                recordingSid: $recordingSid,
                durationSeconds: max(0, $duration),
            );
        }

        return $this->twiml('<Hangup/>');
    }

    private function twiml(string $xml, int $status = 200): Response
    {
        if (! str_starts_with($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'.(str_starts_with($xml, '<Response') ? $xml : '<Response>'.$xml.'</Response>');
        }

        return response($xml, $status)->header('Content-Type', 'text/xml');
    }
}
