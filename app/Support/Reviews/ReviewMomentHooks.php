<?php

namespace App\Support\Reviews;

/** Where the "something really worked" events come from (an observer per product). Registered once at boot; each hook only records a row and can never fail or slow the purchase. */
final class ReviewMomentHooks
{
    public static function register(): void
    {
        if (class_exists(\App\Models\EsimOrder::class)) {
            \App\Models\EsimOrder::saved(function ($o) {
                // Delivered = it has an activation code or QR; orders placed for a merchant widget have no Naara member to ask.
                if ($o->user_id && $o->merchant_client_id === null && $o->isDeliverable() && ! in_array($o->status, ['failed', 'refunded', 'cancelled'], true)) {
                    ReviewMoments::qualify((int) $o->user_id, 'esim_first_purchase', $o->id);
                }
            });
        }
        if (class_exists(\App\Models\SmsOrder::class)) {
            \App\Models\SmsOrder::saved(function ($o) {
                if ($o->status === 'completed' && $o->user_id && $o->wasChanged('status')) {
                    ReviewMoments::qualify((int) $o->user_id, 'sms_verification', $o->id);
                }
            });
        }
        if (class_exists(\App\Models\VirtualNumber::class)) {
            \App\Models\VirtualNumber::saved(function ($n) {
                if ($n->status === 'active' && $n->user_id) {
                    ReviewMoments::qualify((int) $n->user_id, 'virtual_number', $n->id);
                }
            });
        }
        if (class_exists(\App\Models\GiftCardOrder::class)) {
            \App\Models\GiftCardOrder::saved(function ($o) {
                if ($o->status === 'delivered' && $o->user_id) {
                    ReviewMoments::qualify((int) $o->user_id, 'giftcard_purchase', $o->id);
                }
            });
        }
    }
}
