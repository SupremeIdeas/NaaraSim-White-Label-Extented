<?php

namespace App\Console\Commands;

use App\Models\PayoutWebhookEvent;
use App\Support\PayoutSettings;
use Illuminate\Console\Command;

class PayoutsWebhookPruneCommand extends Command
{
    protected $signature = 'payouts:webhook-prune';

    protected $description = 'Drop raw webhook payloads past the retention window (the dedupe row itself is kept)';

    public function handle(): int
    {
        $n = PayoutWebhookEvent::query()
            ->whereNotNull('raw_payload')
            ->where('received_at', '<', now()->subDays(PayoutSettings::webhookPayloadRetentionDays()))
            ->update(['raw_payload' => null]);
        $this->info("Pruned {$n} payload(s).");

        return self::SUCCESS;
    }
}
