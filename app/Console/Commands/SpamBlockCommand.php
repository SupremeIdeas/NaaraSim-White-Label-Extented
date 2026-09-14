<?php

namespace App\Console\Commands;

use App\Services\Voice\SpamReportService;
use Illuminate\Console\Command;

/**
 * Spam-report + auto-block (Prompt 11): the ops control for direct admin
 * action — block a known-bad number immediately (independent of the report
 * threshold), or reverse a false-positive auto-block.
 */
class SpamBlockCommand extends Command
{
    protected $signature = 'spam:block {number : E.164 number, e.g. +15550001234}
        {--unblock : Remove the number from the block list instead}';

    protected $description = 'Block (or unblock) a phone number from being dialed via the in-browser dialer.';

    public function handle(SpamReportService $spam): int
    {
        $number = trim((string) $this->argument('number'));
        if ($number === '') {
            $this->error('A number is required.');

            return self::FAILURE;
        }

        if ($this->option('unblock')) {
            $spam->unblock($number);
            $this->info("Unblocked {$number}.");

            return self::SUCCESS;
        }

        $spam->blockDirectly($number);
        $this->info("Blocked {$number} — it can no longer be dialed.");

        return self::SUCCESS;
    }
}
