<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API webhook (BUILD-4 §7). Two jobs:
 *
 *   GET  — the one-time verification handshake. Meta calls with hub.challenge +
 *          hub.verify_token; we echo the challenge only when the token matches.
 *   POST — event delivery. We verify the X-Hub-Signature-256 HMAC over the RAW
 *          body BEFORE touching the payload (money rule 9), then handle the two
 *          things we care about: delivery statuses (logged) and inbound "STOP"
 *          messages, which flip the sender's opt-in OFF (compliance — a user can
 *          always leave Autopilot straight from WhatsApp).
 *
 * Docs: developers.facebook.com/docs/graph-api/webhooks/getting-started
 */
class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        return $request->isMethod('get') ? $this->verify($request) : $this->handle($request);
    }

    private function verify(Request $request)
    {
        $token = (string) config('services.whatsapp.verify_token', '');

        if ($token !== '' && $request->query('hub_verify_token') === $token) {
            return response((string) $request->query('hub_challenge', ''), 200);
        }

        return response('Forbidden', 403);
    }

    private function handle(Request $request)
    {
        $secret = (string) config('services.whatsapp.app_secret', '');
        $raw = $request->getContent();

        // Verify BEFORE reading the payload. Fails CLOSED (readiness-audit
        // fix, 2026-09-07): an unconfigured secret used to be treated as
        // "nothing to check", letting anyone spoof an inbound STOP message
        // and opt an arbitrary phone number out. Meta signs as "sha256=<hex>".
        abort_if($secret === '', 401);
        $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        abort_unless(hash_equals($expected, $signature), 401);

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['statuses'] ?? [] as $status) {
                    Log::info('WhatsApp delivery status', [
                        'id' => $status['id'] ?? null,
                        'status' => $status['status'] ?? null,
                    ]);
                }

                foreach ($value['messages'] ?? [] as $message) {
                    $this->handleInbound($message);
                }
            }
        }

        // Always 200 quickly so Meta doesn't retry a payload we've accepted.
        return response()->json(['ok' => true]);
    }

    /** Honour an inbound opt-out keyword ("STOP"/"UNSUBSCRIBE") from the user. */
    private function handleInbound(array $message): void
    {
        $from = preg_replace('/\D+/', '', (string) ($message['from'] ?? ''));
        $text = strtoupper(trim((string) ($message['text']['body'] ?? '')));

        if ($from === '' || ! in_array($text, ['STOP', 'UNSUBSCRIBE', 'CANCEL'], true)) {
            return;
        }

        // Match against either the dedicated WhatsApp number or the phone field.
        User::query()
            ->where('whatsapp_opt_in', true)
            ->where(function ($q) use ($from) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(whatsapp_number,' ',''),'+',''),'-','') = ?", [$from])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-','') = ?", [$from]);
            })
            ->update(['whatsapp_opt_in' => false]);
    }
}
