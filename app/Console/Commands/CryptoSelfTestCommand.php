<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Services\Crypto\Health\CryptoSelfTest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Crypto top-up self-test: the proof that the build is genuine and healthy, run hourly in production. Alerts the admin on any failure (once per 6 hours per check). */
class CryptoSelfTestCommand extends Command
{
    protected $signature = 'crypto:selftest {--quiet-ok : print nothing when everything passes}';

    protected $description = 'Verify the crypto top-up end to end (maths, signatures, contracts, endpoints, scanner, ledger) and alert on any failure';

    public function handle(CryptoSelfTest $t): int
    {
        $checks = $t->run();
        $worst = CryptoSelfTest::worst($checks);
        foreach ($checks as $c) {
            if (! ($this->option('quiet-ok') && $worst === 'ok')) {
                $this->line(strtoupper($c['status']).'  '.$c['title'].': '.$c['detail']);
            }
            if ($c['status'] === 'fail' && Cache::add('crypto:selftest:alert:'.$c['key'], 1, now()->addHours(6))) {
                AlertAdminJob::dispatch(code: 'crypto_selftest_failed', message: 'Crypto top-up self-test failed: '.$c['title'].'. '.$c['detail'], context: ['check' => $c['key']]);
            }
        }

        return $worst === 'fail' ? self::FAILURE : self::SUCCESS;
    }
}
