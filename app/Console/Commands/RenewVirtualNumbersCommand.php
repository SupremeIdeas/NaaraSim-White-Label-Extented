<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Setting;
use App\Models\VirtualNumber;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Monthly billing for permanent numbers (Naara Line). Run daily by the scheduler.
 *   - Charges every subscription whose next_billing_date has arrived, from the
 *     user's wallet, then advances the date one month (idempotent per month).
 *   - If the wallet is short, the number enters a grace period (past_due); if the
 *     user tops up before grace ends, the next run charges it and reactivates.
 *   - Once grace lapses, the number is released at the provider and marked
 *     expired (it moves to the dashboard Archive) — so we never keep paying a
 *     provider for a number the user isn't paying us for.
 * Money-safety: every charge is idempotent by "vnum-renew:{id}:{YYYY-MM}" and
 * runs through WalletService; no cost is ever exposed.
 */
class RenewVirtualNumbersCommand extends Command
{
    protected $signature = 'virtual:renew';

    protected $description = 'Charge due virtual-number subscriptions and release lapsed ones.';

    public function handle(WalletService $wallet): int
    {
        $graceDays = (int) Setting::getValue('numbers.renewal_grace_days', 3);
        $billed = 0;
        $lapsed = 0;

        // 1) Bill due subscriptions (active, or past_due users who may have topped up).
        VirtualNumber::whereIn('status', ['active', 'past_due'])
            ->whereDate('next_billing_date', '<=', today())
            ->with('user')
            ->chunkById(100, function ($numbers) use ($wallet, $graceDays, &$billed) {
                foreach ($numbers as $vn) {
                    if (! $vn->user) {
                        continue;
                    }
                    // Keyed on the PERIOD being charged (next_billing_date), not the
                    // calendar month the command happens to run in — if a run is ever
                    // missed and this fires late (or twice) in the same real month, a
                    // now()-keyed reference would make WalletService's idempotency
                    // return the existing transaction with no new debit, while the
                    // code below still advances next_billing_date — silently forgiving
                    // a month's charge. Keying on the actual due date guarantees each
                    // distinct billing period is charged exactly once, however late.
                    $ref = "vnum-renew:{$vn->id}:".Carbon::parse($vn->next_billing_date)->format('Y-m');
                    try {
                        $wallet->debit($vn->user, (float) $vn->monthly_retail, 'USD', [
                            'reference' => $ref,
                            'description' => 'Virtual number monthly renewal',
                        ]);
                        $vn->forceFill([
                            'status' => 'active',
                            'expires_at' => null,
                            'next_billing_date' => Carbon::parse($vn->next_billing_date)->addMonthNoOverflow()->toDateString(),
                        ])->save();
                        $billed++;
                    } catch (InsufficientBalanceException) {
                        if ($vn->status !== 'past_due') {
                            $vn->forceFill([
                                'status' => 'past_due',
                                'expires_at' => now()->addDays($graceDays),
                            ])->save();
                        }
                    } catch (\Throwable $e) {
                        Log::warning("virtual:renew failed for {$vn->id}: ".$e->getMessage());
                    }
                }
            });

        // 2) Release numbers whose grace period has lapsed.
        VirtualNumber::where('status', 'past_due')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($numbers) use (&$lapsed) {
                foreach ($numbers as $vn) {
                    try {
                        app("number.{$vn->provider}")->releaseNumber((string) $vn->sid);
                    } catch (\Throwable) {
                        // best-effort; we stop billing regardless
                    }
                    $vn->forceFill(['status' => 'expired'])->save();
                    $lapsed++;
                }
            });

        $this->info("Renewed {$billed} number(s); released {$lapsed} lapsed.");

        return self::SUCCESS;
    }
}
