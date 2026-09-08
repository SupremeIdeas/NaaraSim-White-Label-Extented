<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use App\Services\Pricing\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Unified USD Wallet (Part B §3.3, Migration 2) — one-time backfill that
 * converts every wallet's legacy ngn_balance into the spendable usd_balance
 * at the live rate, then zeroes ngn_balance so it becomes purely historical
 * (matching the "Legacy NGN balance" treatment already shipped on the Wallet
 * page, and the post-Part-B behaviour where no new top-up ever grows it).
 *
 * Writes directly to the wallet row rather than through
 * WalletService::credit()/debit(), because this is an internal balance
 * conversion, not a new deposit or spend — routing it through apply() would
 * incorrectly inflate the total_deposits/total_spent lifetime stats. Still
 * atomic (cache lock + row lock) and naturally idempotent: a wallet whose
 * ngn_balance is already 0 (migrated, or emptied concurrently) is skipped.
 */
class MigrateNgnToUsdCommand extends Command
{
    protected $signature = 'wallet:migrate-ngn-to-usd {--dry-run : Preview the conversion without writing anything}';

    protected $description = 'One-time backfill: convert every legacy ngn_balance into usd_balance at the live rate (Unified USD Wallet, Part B).';

    public function handle(CurrencyService $fx): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rate = $fx->getUsdToNgn();
        $this->info('Converting at 1 USD = '.number_format($rate, 2).' NGN'.($dryRun ? ' (dry run — nothing will be written)' : '.'));

        $wallets = UserWallet::query()->where('ngn_balance', '>', 0)->get(['id', 'user_id', 'ngn_balance']);
        if ($wallets->isEmpty()) {
            $this->info('No legacy NGN balances to migrate.');

            return self::SUCCESS;
        }

        $converted = 0;
        foreach ($wallets as $row) {
            $ngn = (float) $row->ngn_balance;
            $usd = round($ngn / $rate, 4);
            $this->line("  user #{$row->user_id}: NGN ".number_format($ngn, 2).' -> USD '.number_format($usd, 4));

            if ($dryRun) {
                $converted++;

                continue;
            }

            $user = User::find($row->user_id);
            if ($user === null) {
                continue;
            }

            $migrated = Cache::lock("wallet:{$user->id}", 10)->block(5, function () use ($user, $usd) {
                return DB::transaction(function () use ($user, $usd) {
                    $wallet = UserWallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
                    if ($wallet === null || (float) $wallet->ngn_balance <= 0) {
                        return false; // already migrated or emptied concurrently
                    }

                    $ngnBefore = round((float) $wallet->ngn_balance, 4);
                    $usdBefore = round((float) $wallet->usd_balance, 4);
                    $usdAfter = round($usdBefore + $usd, 4);

                    $wallet->ngn_balance = 0;
                    $wallet->usd_balance = $usdAfter;
                    $wallet->save();

                    $reference = 'ngn-to-usd-migration:'.$user->id;
                    $description = 'Unified USD Wallet migration: legacy NGN balance converted to USD';

                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'debit',
                        'amount' => $ngnBefore,
                        'currency' => 'NGN',
                        'balance_before' => $ngnBefore,
                        'balance_after' => 0,
                        'reference' => $reference.':ngn',
                        'description' => $description,
                        'status' => 'completed',
                    ]);
                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'credit',
                        'amount' => $usd,
                        'currency' => 'USD',
                        'paid_amount' => $ngnBefore,
                        'paid_currency' => 'NGN',
                        'balance_before' => $usdBefore,
                        'balance_after' => $usdAfter,
                        'reference' => $reference.':usd',
                        'description' => $description,
                        'status' => 'completed',
                    ]);

                    return true;
                });
            });

            if ($migrated) {
                $converted++;
            }
        }

        $this->info(($dryRun ? 'Would migrate ' : 'Migrated ').$converted.' wallet(s).');

        return self::SUCCESS;
    }
}
