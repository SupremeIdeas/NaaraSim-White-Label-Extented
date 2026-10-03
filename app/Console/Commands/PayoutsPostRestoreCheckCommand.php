<?php

namespace App\Console\Commands;

use App\Services\Payouts\Hardening\PostRestoreCheck;
use App\Support\PayoutSettings;
use App\Models\Setting;
use Illuminate\Console\Command;

class PayoutsPostRestoreCheckCommand extends Command
{
    protected $signature = 'payouts:post-restore-check
        {--since= : Backup time (e.g. "2026-10-01 03:00"); includes requests finalised after it}
        {--apply : Apply the provider answers through the normal confirm/fail paths}';

    protected $description = 'After a DB restore: pause payouts, compare every open/recent payout with its provider, report mismatches';

    public function handle(PostRestoreCheck $check): int
    {
        // Step 2 of the restore protocol: nothing may move while we reconcile.
        Setting::setValue(PayoutSettings::AUTO_APPROVAL, false);
        Setting::setValue(PayoutSettings::FLAG, false);
        $this->warn('Payouts and auto-approval are now OFF. Re-enable them in Admin -> Payouts once this report is clean.');

        $since = $this->option('since') ? \Illuminate\Support\Carbon::parse($this->option('since')) : null;
        $r = $check->run($since, (bool) $this->option('apply'));

        $this->info("Checked {$r['checked']} request(s); ".count($r['mismatches']).' mismatch(es); '.count($r['unverifiable']).' could not be verified.');
        foreach ($r['mismatches'] as $m) {
            $this->line("  #{$m['request_id']} {$m['provider']}: we say {$m['our_status']}, provider says {$m['provider_says']} -> {$m['action']}");
        }
        if ($r['unverifiable'] !== []) {
            $this->warn('Verify by hand at the provider: #'.implode(', #', $r['unverifiable']));
        }

        return $r['mismatches'] === [] && $r['unverifiable'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
