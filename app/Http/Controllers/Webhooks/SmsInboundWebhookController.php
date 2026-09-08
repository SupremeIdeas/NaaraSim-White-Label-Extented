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
 */
class SmsInboundWebhookController extends Controller
{
    private const PROVIDERS = ['twilio', 'telnyx', 'fivesim', 'herosms', 'getatext'];

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return response()->json(['ok' => false], 404);
        }

        // Verify BEFORE trusting the payload (money/PII-safety rule 9).
        $secret = (string) config("services.$provider.webhook_token", config("services.$provider.inbound_token", ''));
        $provided = (string) ($request->header('X-Webhook-Token') ?? $request->query('token', ''));
        if ($secret === '' || ! hash_equals($secret, $provided)) {
            return response()->json(['ok' => false], 401);
        }

        $to = $this->firstOf($request, ['to', 'To', 'to_number', 'destination']);
        $from = $this->firstOf($request, ['from', 'From', 'from_number', 'sender', 'msisdn']);
        $body = $this->firstOf($request, ['body', 'Body', 'text', 'message', 'content']);
        $media = $this->firstOf($request, ['media', 'MediaUrl0', 'attachment_url', 'media_url']);
        $ref = $this->firstOf($request, ['message_id', 'MessageSid', 'sms_id', 'id', 'provider_ref']);

        // Resolve the receiving Naara Line → its owner. An unmatched number is a
        // 200 no-op (never a retry storm) — it just isn't ours.
        $line = $to ? VirtualNumber::where('phone_number', $this->normalise($to))->first() : null;
        if (! $line || ! $from) {
            return response()->json(['ok' => true, 'matched' => false]);
        }

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
