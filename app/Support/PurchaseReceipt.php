<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\PurchaseReceiptNotification;

/**
 * Sends a purchase receipt (BUILD-7 §1) after a completed order. Guarded so a
 * receipt failure can NEVER break or roll back the money path it follows — the
 * order already succeeded; the receipt is best-effort + queued.
 */
class PurchaseReceipt
{
    public static function send(User $user, string $item, float $usd, string $reference, ?string $country = null): void
    {
        try {
            $user->notify(new PurchaseReceiptNotification($item, round($usd, 2), $reference, $country ?? $user->country_code));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
