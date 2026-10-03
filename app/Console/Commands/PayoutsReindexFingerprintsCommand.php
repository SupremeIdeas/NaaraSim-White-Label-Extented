<?php

namespace App\Console\Commands;

use App\Models\PayoutAccount;
use App\Models\PayoutAccountFingerprint;
use Illuminate\Console\Command;

class PayoutsReindexFingerprintsCommand extends Command
{
    protected $signature = 'payouts:reindex-fingerprints';

    protected $description = 'Recompute destination blind indexes after rotating PAYOUT_FP_KEY (idempotent, resumable)';

    public function handle(): int
    {
        $n = 0;
        PayoutAccount::query()->orderBy('id')->chunkById(200, function ($accounts) use (&$n) {
            foreach ($accounts as $a) {
                $hash = PayoutAccount::lookupHashFor((string) $a->account_number);
                if ($a->lookup_hash === $hash) {
                    continue;
                }
                // Erased accounts only hold a masked number: leave their historical fingerprint alone.
                if (str_starts_with((string) $a->account_number, '*')) {
                    continue;
                }
                $a->forceFill(['lookup_hash' => $hash])->saveQuietly();
                PayoutAccountFingerprint::updateOrCreate(
                    ['provider' => $a->provider, 'user_id' => $a->user_id, 'payout_account_id' => $a->id],
                    ['fingerprint' => $hash],
                );
                $n++;
            }
        });
        $this->info("Re-indexed {$n} account(s).");

        return self::SUCCESS;
    }
}
