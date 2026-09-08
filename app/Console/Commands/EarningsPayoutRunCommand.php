<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Models\Merchant;
use App\Models\PayoutAccount;
use App\Models\ReferralEarning;
use App\Models\StaffEarning;
use App\Models\User;
use App\Services\Merchants\MerchantWithdrawalService;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Referrals\ReferralWithdrawalService;
use App\Services\Staff\StaffWithdrawalService;
use App\Support\PayoutSettings;
use Illuminate\Console\Command;

/**
 * Automatic recurring payouts for merchant + referral earners (NAARA-BUILD-22
 * §2/§6, Frank's "automatic recurring" choice). Partners have their own run
 * (partners:payout-run); this pays the OTHER two earner types on the same engine.
 *
 * Per eligible earner with a verified payout account and a balance at/above the
 * minimum: send the full available balance. An earner who has passed the unified
 * free-payout threshold (§3) without KYC-L2 is SKIPPED, not force-paid — the
 * shared dashboard shows them the "verify to keep withdrawing" prompt. Every
 * payout holds its bucket first and carries a unique reference, so a re-run never
 * double-pays (the held balance is simply gone on the next pass).
 */
class EarningsPayoutRunCommand extends Command
{
    protected $signature = 'payouts:earnings-run';

    protected $description = 'Automatically pay out eligible merchant + referral earnings balances';

    public function handle(
        MerchantWithdrawalService $merchantWd,
        ReferralWithdrawalService $referralWd,
        StaffWithdrawalService $staffWd,
        PayoutThreshold $threshold,
    ): int {
        if (! PayoutSettings::enabled()) {
            $this->info('Payouts are disabled — nothing to do.');

            return self::SUCCESS;
        }

        $min = PayoutSettings::minWithdrawal();
        $paid = 0;
        $skipped = 0;

        // ── Merchants ────────────────────────────────────────────────────────
        Merchant::where('status', Merchant::ACTIVE)->with('owner')->chunkById(100, function ($merchants) use ($merchantWd, $threshold, $min, &$paid, &$skipped) {
            foreach ($merchants as $merchant) {
                $owner = $merchant->owner;
                if (! $owner) {
                    continue;
                }
                $balance = $merchantWd->availableUsd($merchant);
                if ($balance < $min) {
                    continue;
                }
                $account = $this->verifiedAccount($owner);
                if (! $account) {
                    continue; // owed — paid on a later run once an account is verified
                }
                if (! $threshold->canWithdraw($owner)) {
                    $skipped++;

                    continue; // needs KYC — dashboard prompts them
                }
                try {
                    $merchantWd->request($merchant, $account, $balance);
                    // Autopilot mode sends immediately; manual leaves it PENDING for admin.
                    $paid++;
                } catch (\Throwable $e) {
                    $this->warn("Merchant {$merchant->id}: {$e->getMessage()}");
                    $this->alertFailure('merchant', $merchant->id, $e);
                }
            }
        });

        // ── Referral earners ─────────────────────────────────────────────────
        // Chunked over Users (not a per-id User::find in a loop) so this stays
        // flat as the platform scales, matching the merchant leg above.
        User::whereIn('id', ReferralEarning::query()->select('user_id')->distinct())
            ->chunkById(100, function ($users) use ($referralWd, $threshold, $min, &$paid, &$skipped) {
                foreach ($users as $user) {
                    $balance = $referralWd->availableUsd($user);
                    if ($balance < $min) {
                        continue;
                    }
                    $account = $this->verifiedAccount($user);
                    if (! $account) {
                        continue;
                    }
                    if (! $threshold->canWithdraw($user)) {
                        $skipped++;

                        continue;
                    }
                    try {
                        $referralWd->request($user, $account, $balance, autoSend: true);
                        $paid++;
                    } catch (\Throwable $e) {
                        $this->warn("Referral earner {$user->id}: {$e->getMessage()}");
                        $this->alertFailure('referral', $user->id, $e);
                    }
                }
            });

        // ── Staff (KYC-exempt, BUILD-23) ─────────────────────────────────────
        User::whereIn('id', StaffEarning::query()->select('user_id')->distinct())
            ->chunkById(100, function ($staffers) use ($staffWd, $min, &$paid) {
                foreach ($staffers as $staff) {
                    $balance = $staffWd->availableUsd($staff);
                    if ($balance < $min) {
                        continue;
                    }
                    $account = $this->verifiedAccount($staff);
                    if (! $account) {
                        continue;
                    }
                    try {
                        $staffWd->request($staff, $account, $balance, autoSend: true);
                        $paid++;
                    } catch (\Throwable $e) {
                        $this->warn("Staff earner {$staff->id}: {$e->getMessage()}");
                        $this->alertFailure('staff', $staff->id, $e);
                    }
                }
            });

        $this->info("Earnings payout run complete: {$paid} paid, {$skipped} awaiting KYC.");

        return self::SUCCESS;
    }

    private function verifiedAccount(User $user): ?PayoutAccount
    {
        return PayoutAccount::where('user_id', $user->id)
            ->where('is_verified', true)
            ->latest('id')
            ->first();
    }

    /**
     * A failed automatic payout must never be invisible — the cron's own
     * stdout is discarded in production (routes/console.php), so $this->warn()
     * alone leaves no trace. Money rule 7: a failed payout is alerted, not
     * silently dropped until the next run.
     */
    private function alertFailure(string $earnerType, int $userId, \Throwable $e): void
    {
        AlertAdminJob::dispatch(
            code: 'earnings_payout_failed',
            message: "Automatic {$earnerType} payout for user {$userId} failed: {$e->getMessage()}",
            context: ['earner_type' => $earnerType, 'user_id' => $userId, 'exception' => $e::class],
        );
    }
}
