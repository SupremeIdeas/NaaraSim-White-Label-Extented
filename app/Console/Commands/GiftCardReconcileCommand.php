<?php

namespace App\Console\Commands;

use App\Models\GiftCardOrder;
use App\Services\GiftCards\GiftCardCatalogueSyncService;
use App\Services\GiftCards\GiftCardOrderService;
use App\Services\GiftCards\GiftCardStatusCheckable;
use App\Support\Auditor;
use Illuminate\Console\Command;
use Throwable;

/**
 * Recovery path for a Naara Gift order stuck in 'processing' because its
 * provider's async-delivery webhook never arrived (dropped delivery, endpoint
 * misconfigured, etc). Polls the provider's REAL order-status endpoint —
 * only for providers that implement GiftCardStatusCheckable (Reloadly,
 * Bitrefill, Tillo; Zendit has no confirmed status-by-reference endpoint, so
 * its orders rely on the webhook alone, same as before this command existed).
 *
 * Never touches an order that's already terminal, and never re-charges —
 * this only reads a status and either fills the receipt (delivered) or
 * refunds (failed), exactly like the webhook controller does.
 */
class GiftCardReconcileCommand extends Command
{
    protected $signature = 'giftcards:reconcile-processing {--minutes=10 : only orders processing for at least this long}';

    protected $description = 'Poll provider order-status for Naara Gift orders stuck processing past a webhook-miss threshold';

    public function handle(GiftCardCatalogueSyncService $sync, GiftCardOrderService $orders): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        $stuck = GiftCardOrder::where('status', GiftCardOrder::STATUS_PROCESSING)
            ->where('created_at', '<=', $threshold)
            ->whereNotNull('provider_tx_id')
            ->get();

        $checked = 0;
        $resolved = 0;

        foreach ($stuck as $order) {
            $provider = $sync->provider($order->provider);
            if (! $provider instanceof GiftCardStatusCheckable) {
                continue;
            }

            $checked++;

            try {
                $result = $provider->orderStatus((string) $order->provider_tx_id);
            } catch (Throwable $e) {
                Auditor::log('giftcard.reconcile_check_failed', GiftCardOrder::class, $order->id, ['error' => $e->getMessage()]);

                continue;
            }

            if ($result['status'] === 'delivered') {
                $order->update([
                    'status' => GiftCardOrder::STATUS_DELIVERED,
                    'receipt' => array_merge((array) $order->receipt, $result['receipt']),
                ]);
                Auditor::log('giftcard.reconciled', GiftCardOrder::class, $order->id, ['status' => 'delivered']);
                $resolved++;
            } elseif ($result['status'] === 'failed') {
                $orders->failAndRefund($order);
                Auditor::log('giftcard.reconciled', GiftCardOrder::class, $order->id, ['status' => 'failed']);
                $resolved++;
            }
            // Still 'processing' at the provider — leave it, next run checks again.
        }

        $this->info("Checked {$checked} stuck order(s), resolved {$resolved}.");

        return self::SUCCESS;
    }
}
