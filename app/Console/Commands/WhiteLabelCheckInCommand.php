<?php

namespace App\Console\Commands;

use App\Services\Updater\WhiteLabelUpdateClient;
use App\Support\Activation\LicenseState;
use Illuminate\Console\Command;

/**
 * The heartbeat. Reports to the original platform where this install lives (domain, version) and re-reads what the licence includes. Scheduled every few
 * hours; does nothing until the install has been activated. A failure is recorded on the activation screen and never changes what the site does.
 */
class WhiteLabelCheckInCommand extends Command
{
    protected $signature = 'whitelabel:check-in';

    protected $description = 'Report this install to the original platform and refresh its licence entitlement';

    public function handle(WhiteLabelUpdateClient $client): int
    {
        if (! LicenseState::isActivated()) {
            $this->line('Not activated yet: nothing to report.');

            return self::SUCCESS;
        }
        $r = $client->refreshEntitlement();
        $this->line($r['ok'] ? 'Checked in. Licence level: '.($r['level'] ?? 'unknown') : 'Check-in failed: '.$r['error']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
