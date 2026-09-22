<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\AccountService;
use Illuminate\Console\Command;

/**
 * Erasure fix Phase A, stage 3: the real, final purge. An anonymized account
 * (erase() already ran — PII gone, financial/order records retained) becomes
 * eligible for true deletion once its admin-configured retention_purge_due_at
 * has passed. Until then this command leaves it untouched — anonymization is
 * not a purge, it's a hold.
 */
class PurgeErasedAccountsCommand extends Command
{
    protected $signature = 'account:purge-erased';

    protected $description = 'Permanently delete anonymized accounts whose retention window has elapsed.';

    public function handle(AccountService $accounts): int
    {
        $due = User::whereNotNull('anonymized_at')
            ->whereNotNull('retention_purge_due_at')
            ->where('retention_purge_due_at', '<=', now())
            ->get();

        foreach ($due as $user) {
            $accounts->purge($user);
        }

        $this->info("Purged {$due->count()} anonymized account(s) past their retention window.");

        return self::SUCCESS;
    }
}
