<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Models\Partner;
use App\Services\Partners\PartnerPayoutService;
use App\Support\PartnerSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Partner profit-share payout run. Scheduled daily; each active partner is only
 * paid when a full cadence period (weekly/monthly) has elapsed, so a daily run
 * safely accrues + pays exactly on the partner's own cadence with no double
 * counting (idempotent per period). Manual-mode payouts wait for admin approval;
 * auto-mode payouts transfer immediately.
 */
class PartnerPayoutRunCommand extends Command
{
    protected $signature = 'partners:payout-run';

    protected $description = 'Accrue and pay out due partner profit-share periods.';

    public function handle(PartnerPayoutService $service): int
    {
        if (! PartnerSettings::enabled()) {
            $this->info('Partner program is disabled — nothing to run.');

            return self::SUCCESS;
        }

        $paid = 0;
        Partner::where('status', Partner::ACTIVE)->with('owner')->chunkById(100, function ($partners) use ($service, &$paid) {
            foreach ($partners as $partner) {
                try {
                    $result = $service->runPartner($partner);
                    if ($result['periods'] > 0 || $result['payout']) {
                        $paid++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("partners:payout-run failed for {$partner->id}: ".$e->getMessage());
                    // The cron's own stdout is discarded in production
                    // (routes/console.php) — a failed payout must not be
                    // invisible until someone happens to check the log file.
                    AlertAdminJob::dispatch(
                        code: 'partner_payout_failed',
                        message: "Partner payout run failed for partner {$partner->id}: {$e->getMessage()}",
                        context: ['partner_id' => $partner->id, 'exception' => $e::class],
                    );
                }
            }
        });

        $this->info("Processed {$paid} partner(s).");

        return self::SUCCESS;
    }
}
