<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\RecordInboundMessageJob;
use App\Models\VirtualNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound SMS webhook (Numbers overhaul §1). Mirrors the established
 * verify-before-trust pattern (GetatextWebhookController): the shared token is
 * checked BEFORE the payload is touched, then a queued job records the message —
 * never processed inline. The delivery is idempotent per (provider, provider_ref).
 *
 * Field mapping is generic across providers (from/to/body/media use the common
 * key spellings), so a new SMS provider needs only a config token, not a new
 * controller.
 *
 * Prompt 12 — adding a provider to PROVIDERS below also requires adding it
 * to: `PermanentNumberRouter::$lane`, `ProviderModels::MODELS[...]['lane']`
 * (+ `PROVIDER_KEY_FIELD`), `ProviderHealth::PROVIDERS`.
 */
class SmsInboundWebhookController extends Controller
{
    private const PROVIDERS = ['twilio', 'telnyx', 'fivesim', 'herosms', 'getatext', 'plivo', 'vonage', 'sinch'];

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return response()->json(['ok' => false], 404);
        }

        // Verify BEFORE trusting the payload (money/PII-safety rule 9). Twilio and Telnyx sign every webhook, so a valid signature is accepted on its own;
        // everything else (and a signing provider without its key set) uses the shared secret token.
        $signed = in_array($provider, ['twilio', 'telnyx'], true) && $request->hasHeader($provider === 'telnyx' ? 'telnyx-signature-ed25519' : 'X-Twilio-Signature')
            && app('number.'.$provider)->verifyWebhook($request, $request->url());
        if (! $signed) {
            $secret = (string) config("services.$provider.webhook_token", config("services.$provider.inbound_token", ''));
            $provided = (string) ($request->header('X-Webhook-Token') ?? $request->query('token', ''));
            if ($secret === '' || ! hash_equals($secret, $provided)) {
                return response()->json(['ok' => false], 401);
            }
        }

        // Telnyx nests the message: {data:{event_type, payload:{id, from:{phone_number}, to:[{phone_number}], text, media:[{url}]}}}. Only message.received is an inbound text.
        if ($provider === 'telnyx' && $request->has('data.event_type')) {
            if ((string) $request->input('data.event_type') !== 'message.received') {
                return response()->json(['ok' => true, 'matched' => false]);
            }
            $request->merge([
                'from' => data_get($request->all(), 'data.payload.from.phone_number'), 'to' => data_get($request->all(), 'data.payload.to.0.phone_number'),
                'text' => data_get($request->all(), 'data.payload.text'), 'media' => data_get($request->all(), 'data.payload.media.0.url'), 'message_id' => data_get($request->all(), 'data.payload.id'),
            ]);
        }

        $to = $this->firstOf($request, ['to', 'To', 'to_number', 'destination']);
        $from = $this->firstOf($request, ['from', 'From', 'from_number', 'sender', 'msisdn']);
        $body = $this->firstOf($request, ['body', 'Body', 'text', 'message', 'content']);
        $media = $this->firstOf($request, ['media', 'MediaUrl0', 'attachment_url', 'media_url']);
        $ref = $this->firstOf($request, ['message_id', 'MessageSid', 'sms_id', 'id', 'provider_ref', 'messageId']);

        // Resolve the receiving Naara Line → its owner. An unmatched number is a
        // 200 no-op (never a retry storm) — it just isn't ours.
        $line = $to ? VirtualNumber::where('phone_number', $this->normalise($to))->first() : null;
        if (! $line || ! $from) {
            return response()->json(['ok' => true, 'matched' => false]);
        }

        // STOP / START from the other party: honoured on this line from now on (the message is still recorded in the thread).
        app(\App\Services\SMS\SmsOptOuts::class)->apply((int) $line->id, $this->normalise($from), $body);

        RecordInboundMessageJob::dispatch(
            userId: $line->user_id,
            virtualNumberId: $line->id,
            fromNumber: $this->normalise($from),
            body: $body,
            attachmentUrl: $media,
            provider: $provider,
            providerRef: $ref,
        );


        return response()->json(['ok' => true]);
    }

    private function firstOf(Request $request, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = $request->input($k);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    private function normalise(string $number): string
    {
        $n = preg_replace('/[^\d+]/', '', $number) ?? $number;

        return $n !== '' ? $n : $number;
    }
}
