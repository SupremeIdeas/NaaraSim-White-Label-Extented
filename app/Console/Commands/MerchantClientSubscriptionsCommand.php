<?php

namespace App\Console\Commands;

use App\Models\MerchantClientSubscription;
use App\Notifications\MerchantSubscriptionDueNotification;
use App\Services\Merchants\MerchantClientService;
use App\Support\Mailer;
use Illuminate\Console\Command;

/**
 * Daily Merchant V2 client-subscription run (client eSIM control):
 *   1. Settle DUE auto-renewals (reserved funds → real debit + re-provision).
 *   2. Expire lapsed subscriptions (no auto-renew) and alert the merchant.
 *   3. Alert merchants about subscriptions due soon (so they can collect + renew).
 * Money moves only through MerchantClientService/WalletService (atomic, refunded
 * on provider failure). Idempotent per run — each transition happens once.
 */
class MerchantClientSubscriptionsCommand extends Command
{
    protected $signature = 'merchant:client-subscriptions {--due-days=3 : Alert window in days}';

    protected $description = 'Settle due auto-renewals, expire lapsed client eSIMs, and alert merchants';

    public function handle(MerchantClientService $service): int
    {
        $dueDays = max(1, (int) $this->option('due-days'));

        // 1. Auto-renew everything due today.
        $renewed = 0;
        MerchantClientSubscription::where('status', MerchantClientSubscription::STATUS_ACTIVE)
            ->where('auto_renew', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['merchant.owner', 'client', 'plan'])
            ->chunkById(50, function ($subs) use ($service, &$renewed) {
                foreach ($subs as $sub) {
                    $ok = $service->renewDueSubscription($sub);
                    $this->notify($sub, $ok ? 'renewed' : 'failed');
                    $renewed += $ok ? 1 : 0;
                }
            });

        // 2. Expire lapsed, non-auto-renew subscriptions.
        $expired = 0;
        MerchantClientSubscription::where('status', MerchantClientSubscription::STATUS_ACTIVE)
            ->where('auto_renew', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with(['merchant.owner', 'client'])
            ->chunkById(100, function ($subs) use (&$expired) {
                foreach ($subs as $sub) {
                    $sub->update(['status' => MerchantClientSubscription::STATUS_EXPIRED]);
                    $this->notify($sub, 'expired');
                    $expired++;
                }
            });

        // 3. Due-soon alerts (once per subscription).
        $alerted = 0;
        MerchantClientSubscription::where('status', MerchantClientSubscription::STATUS_ACTIVE)
            ->where('auto_renew', false)
            ->whereNull('due_alerted_at')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($dueDays)])
            ->with(['merchant.owner', 'client'])
            ->chunkById(100, function ($subs) use (&$alerted) {
                foreach ($subs as $sub) {
                    $this->notify($sub, 'due');
                    $sub->update(['due_alerted_at' => now()]);
                    $alerted++;
                }
            });

        $this->info("Auto-renewed {$renewed}, expired {$expired}, alerted {$alerted}.");

        return self::SUCCESS;
    }

    private function notify(MerchantClientSubscription $sub, string $reason): void
    {
        $owner = $sub->merchant?->owner;
        if ($owner) {
            Mailer::notify($owner, new MerchantSubscriptionDueNotification($sub, $reason));
        }
    }
}
