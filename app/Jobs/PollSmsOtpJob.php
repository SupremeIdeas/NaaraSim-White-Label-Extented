<?php

namespace App\Jobs;

use App\Events\OtpReceived;
use App\Models\SmsOrder;
use App\Services\SMS\OtpStatus;
use App\Services\SMS\SmsProviderInterface;
use App\Services\Wallet\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Polls a provider for an OTP code every 5s up to a 15-minute window
 * (blueprint Section 11.3). On a received code: store it, call finish()
 * (protects 5sim rating), broadcast OtpReceived, mark completed. On timeout:
 * cancel() the order and refund the user — never leave an order hanging.
 *
 * Getatext delivers via webhook when configured; this job is the poll
 * fallback and the primary path for 5sim.
 */
class PollSmsOtpJob implements ShouldQueue
{
    use Queueable;

    private const POLL_SECONDS = 5;

    /**
     * Public so customer-facing trust copy (Naara Verify/Rent modals,
     * PricingPage, the Refund & Reliability Policy page) can state the real
     * refund window instead of a hardcoded guess that would silently drift
     * out of sync if this value ever changes (Prompt 10 §1).
     */
    public const TIMEOUT_MINUTES = 15;

    public function __construct(
        public int $smsOrderId,
        public string $currency = 'USD',
    ) {}

    public function handle(WalletService $wallet): void
    {
        $order = SmsOrder::find($this->smsOrderId);
        if ($order === null || ! in_array($order->status, ['pending', 'waiting'], true)) {
            return; // already resolved
        }

        /** @var SmsProviderInterface $svc */
        $svc = app("number.{$order->provider}");
        $result = $svc->check($order->getatext_id);

        if ($result['status'] === OtpStatus::RECEIVED && ! empty($result['code'])) {
            $order->update([
                'otp_code' => $result['code'],
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $svc->finish($order->getatext_id); // protects 5sim rating
            OtpReceived::dispatch($order->user_id, $order->id, $result['code'], (string) $order->phone_number);

            return;
        }

        // Timed out? Refund + cancel. The auto-refund is the money promise, so it
        // must NOT be gated behind the provider cancel — cancel is best-effort
        // (frees the number / protects our rating) and may fail; the refund is
        // idempotent on the order, so a queue retry can never double it.
        if ($order->ordered_at !== null && $order->ordered_at->diffInMinutes(now()) >= self::TIMEOUT_MINUTES) {
            $order->update(['status' => 'timeout']);

            $wallet->refund($order->user, (float) $order->charged_to_user, $this->currency, [
                'description' => 'OTP not received in time — auto-refund',
                'reference' => "otp-timeout-refund:{$order->id}",
            ]);

            try {
                $svc->cancel($order->getatext_id);
            } catch (\Throwable $e) {
                // Best-effort — the user is already refunded; log and move on.
                Log::warning("OTP cancel failed for order {$order->id}: {$e->getMessage()}");
            }

            return;
        }

        // Still waiting — poll again shortly.
        self::dispatch($this->smsOrderId, $this->currency)->delay(now()->addSeconds(self::POLL_SECONDS));
    }
}
