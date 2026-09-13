<?php

namespace App\Console\Commands;

use App\Services\SMS\NumberBlocklist;
use App\Support\Auditor;
use Illuminate\Console\Command;

/**
 * Recycled-number pre-check (Prompt 11): the ops control for the blocklist.
 * Blocking a number here bars it from ever being re-provisioned as a Naara
 * Line — the honest scope is our OWN inventory (a number we pulled for
 * abuse/complaint), never a cross-provider "clean number" claim.
 */
class BlockNumberCommand extends Command
{
    protected $signature = 'numbers:block {number : E.164 number, e.g. +15550001234}
        {--reason= : Why it is blocked (abuse, complaint, chargeback…)}
        {--provider= : Owning provider, for the record (never surfaced)}
        {--unblock : Remove the number from the blocklist instead}';

    protected $description = 'Block (or unblock) a permanent number from being re-sold as a Naara Line.';

    public function handle(NumberBlocklist $blocklist): int
    {
        $number = trim((string) $this->argument('number'));
        if ($number === '') {
            $this->error('A number is required.');

            return self::FAILURE;
        }

        if ($this->option('unblock')) {
            $blocklist->unblock($number);
            Auditor::log('number.unblocked', 'blocked_number', null, ['number' => $number]);
            $this->info("Unblocked {$number} — it can be provisioned again.");

            return self::SUCCESS;
        }

        $blocklist->block(
            $number,
            reason: $this->option('reason') ?: null,
            provider: $this->option('provider') ?: null,
            source: 'admin',
        );
        Auditor::log('number.blocked', 'blocked_number', null, [
            'number' => $number,
            'reason' => $this->option('reason'),
        ]);
        $this->info("Blocked {$number} — it will never be re-offered as a Naara Line.");

        return self::SUCCESS;
    }
}
