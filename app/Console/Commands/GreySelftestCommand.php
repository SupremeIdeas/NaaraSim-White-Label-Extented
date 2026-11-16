<?php

namespace App\Console\Commands;

use App\PayoutRails\Grey\GreyReadiness;
use Illuminate\Console\Command;

class GreySelftestCommand extends Command
{
    protected $signature = 'grey:selftest {--offline : skip the live reads of Grey}';

    protected $description = 'Score the Grey integration out of 10 (key, host, live reads, webhook, corridors, funding, automation)';

    public function handle(GreyReadiness $readiness): int
    {
        $r = $readiness->run(! $this->option('offline'));
        foreach ($r['checks'] as $c) {
            $this->line(sprintf('  %-5s %-34s %s', strtoupper($c['status']), $c['label'], $c['detail']));
        }
        $this->newLine();
        $this->info("Grey readiness: {$r['score']} of {$r['total']}");

        return collect($r['checks'])->contains(fn ($c) => $c['status'] === 'fail') ? self::FAILURE : self::SUCCESS;
    }
}
