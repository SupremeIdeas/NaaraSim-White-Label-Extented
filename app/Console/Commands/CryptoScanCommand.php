<?php

namespace App\Console\Commands;

use App\Models\Crypto\CryptoDeposit;
use App\Services\Crypto\Chain\ChainRegistry;
use App\Services\Crypto\Deposits\DepositProcessor;
use App\Services\Crypto\Deposits\DepositScanner;
use App\Services\Crypto\Support\DepositStatus;
use Illuminate\Console\Command;

/** On-chain top-up worker: scan every enabled chain, then advance every open deposit. Idle and free while the feature is off. */
class CryptoScanCommand extends Command
{
    protected $signature = 'crypto:scan';

    protected $description = 'Scan the chains for deposits to the receiving addresses and credit verified ones';

    public function handle(ChainRegistry $registry, DepositScanner $scanner, DepositProcessor $processor): int
    {
        if (! $registry->featureOn()) {
            return self::SUCCESS;
        }
        foreach ($registry->enabledChains() as $chain) {
            try {
                $r = $scanner->scan($chain);
                if ($r !== null) {
                    $this->line("chain {$chain->chain_id}: scanned to {$r['scanned_to']}, {$r['found']} new");
                }
            } catch (\Throwable $e) {
                report($e);   // one broken chain never stops the others
                $this->warn("chain {$chain->chain_id}: ".$e->getMessage());
            }
        }
        CryptoDeposit::whereIn('status', [DepositStatus::Detected->value, DepositStatus::Confirming->value, DepositStatus::Verified->value, DepositStatus::Unclaimed->value,
            DepositStatus::PausedHold->value, DepositStatus::CreditPending->value])->orderBy('id')->limit(200)->get()->each(function (CryptoDeposit $d) use ($processor) {
                try {
                    $processor->process($d);
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return self::SUCCESS;
    }
}
