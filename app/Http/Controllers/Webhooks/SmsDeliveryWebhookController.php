<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use App\Services\SMS\SmsDeliveryReconciler;
use Illuminate\Http\Request;

/**
 * Delivery reports (money-safety rule 9: verified BEFORE the payload is trusted).
 *   - Twilio:  form POST with MessageSid + MessageStatus (queued/sent/delivered/undelivered/failed) + ErrorCode, signed X-Twilio-Signature (HMAC-SHA1).
 *   - Telnyx:  JSON `message.finalized` with data.payload.id and to[0].status (delivered / delivery_failed / sending_failed / delivery_unconfirmed), signed
 *              Ed25519 over "{telnyx-timestamp}|{raw body}" with the portal public key.
 * Only FINAL states act (delivered, or failed which refunds once via SmsDeliveryReconciler). In-flight and "unconfirmed" states leave the row alone. Idempotent; an unknown
 * message id is a 200 no-op, never a retry storm.
 */
class SmsDeliveryWebhookController extends Controller
{
    private const PROVIDERS = ['twilio', 'telnyx'];

    public function __invoke(Request $request, string $provider, SmsDeliveryReconciler $reconciler): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return response()->json(['ok' => false], 404);
        }

        $verified = $this->verified($request, $provider);
        [$ref, $rawStatus, $reason] = $this->extract($request, $provider);

        WebhookLog::create([
            'provider' => $provider.':delivery',
            'event_type' => $rawStatus,
            'payload' => $request->except(['token']),
            'signature' => $request->header($provider === 'telnyx' ? 'telnyx-signature-ed25519' : 'X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => $verified,
        ]);
        if (! $verified) {
            return response()->json(['ok' => false], 401);
        }

        $final = match (strtolower($rawStatus)) {
            'delivered', 'success' => 'delivered',
            'failed', 'rejected', 'expired', 'undelivered', 'delivery_failed', 'sending_failed' => 'failed',
            default => null, // queued / sending / sent / delivery_unconfirmed: not final, leave the row alone
        };
        if ($ref !== '' && $final !== null) {
            $msg = OutboundMessage::where('provider', $provider)->where('provider_ref', $ref)->first();
            if ($msg) {
                $reconciler->apply($msg, $final, $reason);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function verified(Request $request, string $provider): bool
    {
        return app('number.'.$provider)->verifyWebhook($request, $request->url());   // Twilio HMAC-SHA1 or Telnyx Ed25519, both checked before the payload is trusted
    }

    /** @return array{0:string,1:string,2:?string} provider message id, raw status, failure reason */
    private function extract(Request $request, string $provider): array
    {
        if ($provider === 'twilio') {
            $code = (string) $request->input('ErrorCode', '');

            return [(string) $request->input('MessageSid', ''), (string) $request->input('MessageStatus', ''), $code !== '' ? 'Carrier error '.$code : null];
        }
        if ($provider === 'telnyx') {
            if ((string) data_get($request->all(), 'data.event_type') !== 'message.finalized') {
                return ['', '', null];   // message.sent and others are progress, not a final report
            }

            return [(string) data_get($request->all(), 'data.payload.id', ''), (string) data_get($request->all(), 'data.payload.to.0.status', ''), data_get($request->all(), 'data.payload.errors.0.title')];
        }

        return ['', '', null];
    }
}
