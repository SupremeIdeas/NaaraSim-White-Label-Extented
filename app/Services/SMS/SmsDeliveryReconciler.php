<?php

namespace App\Services\SMS;

use App\Models\OutboundMessage;
use App\Models\Setting;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Notifications\Notify;

/**
 * Applies a provider's FINAL delivery report to an SMS the person was already charged for (money-safety rules 6 and 7). A final "delivered" is recorded once;
 * a final "failed" refunds the retail the person paid, once, under the same idempotent reference the send-time refund uses, so a repeated or reordered webhook can
 * never pay out twice. A delivered message is never flipped to failed by a late or out-of-order report. The admin switch `sms.refund_on_failed_delivery` (default on)
 * decides whether a carrier-reported failure refunds; the message is marked failed and the sender told either way.
 */
class SmsDeliveryReconciler
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    /** @param 'delivered'|'failed' $final */
    public function apply(OutboundMessage $msg, string $final, ?string $reason = null): string
    {
        if ($msg->status === 'delivered' || ($msg->status === 'failed' && $final === 'failed')) {
            return 'noop';   // already settled; a repeat or a late contradiction changes nothing
        }
        if ($final === 'delivered') {
            $msg->update(['status' => 'delivered']);

            return 'delivered';
        }

        $msg->update(['status' => 'failed']);
        $refunded = false;
        if ((bool) Setting::getValue('sms.refund_on_failed_delivery', true) && $msg->user && (float) $msg->amount_charged > 0 && $msg->reference) {
            $this->wallet->refund($msg->user, (float) $msg->amount_charged, 'USD', ['reference' => 'refund:'.$msg->reference, 'description' => 'Message could not be delivered']);
            $refunded = true;
        }
        if ($msg->user) {
            Notify::event($msg->user, 'message.delivery_failed', ['to' => $msg->to_number, 'reason' => $reason ?: 'The carrier reported it as undelivered.'], dedupe: 'msg-'.$msg->id);
        }
        Auditor::log('sms.delivery_failed', OutboundMessage::class, $msg->id, ['refunded' => $refunded, 'provider' => $msg->provider]);

        return $refunded ? 'failed_refunded' : 'failed';
    }
}
