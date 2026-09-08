<?php

namespace App\Services\SMS;

use App\Exceptions\SmsException;
use App\Jobs\AlertAdminJob;
use App\Models\OutboundMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * MessageSenderService — the money spine of "Send a message from your Naara
 * Line" (Numbers V6 §6). It mirrors the discipline of every other money path
 * (money-safety rules 1–7):
 *
 *   1. Quote the LIVE per-segment retail through PricingEngine before sending —
 *      no price literals, provider cost NEVER exposed.
 *   2. Charge retail × segments with an ATOMIC wallet debit (WalletService →
 *      lockForUpdate + balance_before/after) BEFORE the message leaves.
 *   3. Never charge without delivering: if the debit succeeds but the provider
 *      send fails, the wallet is refunded and the row marked failed. The message
 *      row is written first (queued) so a bookkeeping failure refunds too.
 *   4. Idempotent reference per send; the raw provider is never surfaced.
 */
class MessageSenderService
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly WalletService $wallet,
    ) {}

    /** Hard ceiling on a single message's length (segments), admin-tunable. */
    private function maxSegments(): int
    {
        return max(1, (int) Setting::getValue('sms.max_send_segments', 6));
    }

    /**
     * GSM-style segment count: ≤160 chars is one segment, longer messages are
     * split into 153-char parts (matches how carriers bill concatenated SMS).
     */
    public function segmentsFor(string $body): int
    {
        $len = mb_strlen($body);
        if ($len === 0) {
            return 1;
        }

        return $len <= 160 ? 1 : (int) ceil($len / 153);
    }

    /**
     * Live quote WITHOUT charging: the retail per-segment rate, the segment count
     * for this body, and the resulting total. Cost is NEVER returned (rule 1.2).
     *
     * @return array{retail_per_segment: float, segments: int, retail_total: float}
     */
    public function quote(VirtualNumber $line, string $body, bool $hasMedia = false): array
    {
        $provider = $line->provider;
        $cost = $this->costFor($provider, $line->phone_number, $hasMedia);
        $retail = $this->pricing->calculateSmsRetail($cost, $provider); // MarginGuard-floored
        // An MMS is billed as one media message, not per 160-char segment.
        $segments = $hasMedia ? 1 : min($this->segmentsFor($body), $this->maxSegments());

        return [
            'retail_per_segment' => round($retail, 4),
            'segments' => $segments,
            'retail_total' => round($retail * $segments, 4),
            'is_mms' => $hasMedia,
        ];
    }

    /** Wholesale cost basis: MMS (with media) or SMS, per provider (admin-set). */
    private function costFor(string $provider, string $to, bool $hasMedia): float
    {
        if ($hasMedia) {
            return (float) Setting::getValue(
                "pricing.mms_send_cost.{$provider}",
                $provider === 'telnyx' ? 0.01 : 0.02,
            );
        }

        return (float) app("number.{$provider}")->outboundSmsCost($to);
    }

    /**
     * Send an SMS from the user's own Line. Validates ownership + capability,
     * charges retail atomically, then hands the body to the provider. On any
     * delivery failure the wallet is refunded — never charged without delivering.
     */
    public function send(User $user, VirtualNumber $line, string $to, string $body, ?string $mediaUrl = null): OutboundMessage
    {
        // Ownership + state (a user can only send from their own active Line).
        if ((int) $line->user_id !== (int) $user->id || $line->status !== 'active') {
            throw new SmsException('That number is not available to send from.');
        }
        $caps = (array) $line->capabilities;
        if (array_key_exists('sms', $caps) && ! $caps['sms']) {
            throw new SmsException('This number can’t send text messages.');
        }

        $mediaUrl = $mediaUrl !== null && trim($mediaUrl) !== '' ? trim($mediaUrl) : null;
        $hasMedia = $mediaUrl !== null;
        // MMS only where the carrier actually delivers it (US/CA numbers).
        if ($hasMedia && ! $line->supportsMms()) {
            throw new SmsException('This number can’t send attachments — MMS works on US/Canada lines.');
        }

        $to = trim($to);
        if (! preg_match('/^\+[1-9]\d{6,14}$/', $to)) {
            throw new SmsException('Enter a valid destination in international format, e.g. +2348012345678.');
        }
        $body = trim($body);
        // An attachment can stand alone; a plain SMS needs text.
        if ($body === '' && ! $hasMedia) {
            throw new SmsException('Type a message to send.');
        }

        $provider = $line->provider;
        $svc = app("number.{$provider}");

        $cost = $this->costFor($provider, $to, $hasMedia);
        $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $retail = round($this->pricing->calculateSmsRetail($cost, $provider), 4);

        // MarginGuard backstop (calculateSmsRetail already floors, but never trust).
        if ($retail < $cost + $minProfit) {
            throw new SmsException('Messaging isn’t available on this number right now.');
        }

        $segments = $hasMedia ? 1 : min($this->segmentsFor($body), $this->maxSegments());
        $charge = round($retail * $segments, 4);
        $ref = 'sms-send:'.$user->id.':'.Str::uuid();

        // 1) Charge retail up-front (never send without payment).
        $this->wallet->debit($user, $charge, 'USD', [
            'reference' => $ref,
            'description' => 'Message sent from your Naara Line',
        ]);

        // 2) Record the send in a queued state. A persistence failure here means
        //    nothing has left yet — refund and abort.
        try {
            $message = OutboundMessage::create([
                'user_id' => $user->id,
                'virtual_number_id' => $line->id,
                'to_number' => $to,
                'body' => $body,
                'attachment_url' => $mediaUrl,
                'provider' => $provider,
                'status' => 'queued',
                'segments' => $segments,
                'amount_charged' => $charge,
                'reference' => $ref,
            ]);
        } catch (Throwable $e) {
            $this->wallet->refund($user, $charge, 'USD', ['reference' => "refund:{$ref}", 'description' => 'Message could not be queued']);
            Log::warning('MessageSenderService: could not persist outbound message: '.$e->getMessage());
            throw new SmsException('Something went wrong — your wallet was not charged.');
        }

        // 3) Hand it to the provider. On failure: refund + mark failed (rule 6).
        try {
            $result = $svc->sendSms($line->phone_number, $to, $body, $mediaUrl);
        } catch (Throwable $e) {
            $this->wallet->refund($user, $charge, 'USD', ['reference' => "refund:{$ref}", 'description' => 'Message delivery failed']);
            $message->update(['status' => 'failed']);
            Log::warning("MessageSenderService: {$provider} send failed: ".$e->getMessage());
            throw new SmsException('That message couldn’t be sent — your wallet was refunded.');
        }

        // 4) Delivered. Persist the provider ref. If THIS update somehow fails the
        //    message is already out (delivered), so we keep the charge and alert.
        try {
            $message->update([
                'status' => 'sent',
                'provider_ref' => (string) ($result['provider_ref'] ?? ''),
            ]);
        } catch (Throwable $e) {
            AlertAdminJob::dispatch(
                code: 'sms_send_bookkeeping',
                message: "Sent SMS {$ref} for user {$user->id} but failed to record the provider ref.",
                context: ['user_id' => $user->id, 'reference' => $ref],
            );
        }

        return $message->refresh();
    }
}
