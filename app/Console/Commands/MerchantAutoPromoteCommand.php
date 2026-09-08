<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Models\User;
use App\Services\Merchants\MerchantService;
use App\Support\MerchantSettings;
use Illuminate\Console\Command;

/**
 * Auto-promote eligible users to Merchant V1 (BUILD-4 §4.3). Only runs when the
 * admin has switched it on (off by default — the recommended flow is the manual
 * "Ready to promote" queue). Promotes users who meet an eligibility path and
 * aren't already an active merchant, reusing MerchantService::promote (same role
 * grant + audit + celebration as a one-click promotion). Requires a system admin
 * to attribute the action to.
 */
class MerchantAutoPromoteCommand extends Command
{
    protected $signature = 'merchants:auto-promote {--limit=50 : max promotions per run}';

    protected $description = 'Auto-promote eligible users to Merchant V1 (when enabled by admin)';

    public function handle(MerchantService $merchants): int
    {
        if (! MerchantSettings::enabled() || ! MerchantSettings::autoPromoteEnabled()) {
            $this->info('Auto-promote is off — nothing to do.');

            return self::SUCCESS;
        }

        $admin = User::role('super_admin')->orderBy('id')->first()
            ?? User::role('admin')->orderBy('id')->first();
        if ($admin === null) {
            $this->warn('No admin to attribute promotions to.');

            return self::SUCCESS;
        }

        $minSpend = MerchantSettings::minSpendUsd();
        $minReferrals = MerchantSettings::minReferrals();

        $promoted = 0;
        User::query()
            ->whereDoesntHave('merchantAccount', fn ($m) => $m->where('status', 'active'))
            ->where(fn ($w) => $w
                ->whereNotNull('merchant_enrollment_paid_at')
                ->orWhereHas('wallet', fn ($wl) => $wl->where('total_spent', '>=', $minSpend))
                ->orHas('referralsMade', '>=', $minReferrals))
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (User $user) use ($merchants, $admin, &$promoted) {
                // One failing promotion must never abort the rest of the
                // batch — every other sweep command in this file isolates
                // per-row failures the same way.
                try {
                    $merchants->promote($user, 'v1', $admin, 'Auto-promoted (eligibility met)');
                    $promoted++;
                } catch (\Throwable $e) {
                    AlertAdminJob::dispatch(
                        code: 'merchant_auto_promote_failed',
                        message: "Auto-promotion to Merchant V1 failed for user {$user->id}: {$e->getMessage()}",
                        context: ['user_id' => $user->id, 'exception' => $e::class],
                    );
                }
            });

        $this->info("Auto-promoted {$promoted} user(s) to Merchant V1.");

        return self::SUCCESS;
    }
}
