<?php

namespace App\Services\Crypto\Deposits;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoDepositEvent;
use App\Models\Crypto\CryptoScanCursor;
use App\Services\Crypto\Chain\AbiDecoder;
use App\Services\Crypto\Chain\ChainRegistry;
use App\Services\Crypto\Chain\RpcException;
use App\Services\Crypto\Chain\Units;
use App\Services\Crypto\Support\DepositStatus;
use Illuminate\Database\QueryException;

/**
 * Reads ERC-20 Transfer logs addressed to OUR deposit addresses and records each as a `detected` deposit. It only RECORDS: nothing here moves money.
 * What may be recorded comes from the admin tables (ChainRegistry), never from the feed: a log from a contract that is not on the token allow-list, a malformed
 * Transfer, a removed (reorged) log or a transfer to some other address is ignored. Re-scanning a range is harmless: identity is (chain, tx, log index), unique in the database.
 */
class DepositScanner
{
    public function __construct(private readonly ChainRegistry $registry) {}

    /** @return array{scanned_to: int, found: int}|null null = nothing to do (feature off, no endpoint, no address or token) */
    public function scan(CryptoChain $chain): ?array
    {
        $id = (int) $chain->chain_id;
        if (! $this->registry->watching($id) || ($pool = $this->registry->pool($id)) === null) {
            return null;
        }
        $addresses = $this->registry->scannedAddresses($id);
        $contracts = $this->registry->tokens($id)->pluck('contract')->map('strtolower')->all();
        if ($addresses === [] || $contracts === []) {
            return null;
        }
        $client = $pool->primary();
        $head = $client->blockNumber();
        $cursor = CryptoScanCursor::firstOrCreate(['chain_id' => $id], ['last_scanned_block' => 0, 'last_head' => 0, 'lag_blocks' => 0]);
        // First run: start at the head. History before the feature went live is never back-credited.
        $from = $cursor->last_scanned_block > 0 ? $cursor->last_scanned_block + 1 : $head;
        $found = 0;
        $range = max(1, (int) $chain->max_log_range);
        $topics = [AbiDecoder::TRANSFER_TOPIC, null, array_map([AbiDecoder::class, 'padAddress'], $addresses)];
        $last = $cursor->last_scanned_block;
        $start = $from;
        while ($start <= $head) {
            $end = min($head, $start + $range - 1);
            try {
                $logs = $client->getLogs($start, $end, $contracts, $topics);
            } catch (RpcException $e) {
                if ($e->isRangeError() && $range > 50) {
                    $range = intdiv($range, 2);   // the provider wants a smaller window: halve it and retry from the same start

                    continue;
                }
                $cursor->forceFill(['last_head' => $head, 'lag_blocks' => max(0, $head - $last), 'updated_at' => now()])->save();
                throw $e;
            }
            foreach ($logs as $log) {
                $found += $this->record($id, $log, $addresses) ? 1 : 0;
            }
            $last = $end;
            $start = $end + 1;
            $cursor->forceFill(['last_scanned_block' => $last, 'last_head' => $head, 'lag_blocks' => max(0, $head - $last), 'updated_at' => now()])->save();
        }

        return ['scanned_to' => $last, 'found' => $found];
    }

    /** @param list<string> $ours */
    private function record(int $chainId, array $log, array $ours): bool
    {
        $t = AbiDecoder::decodeTransfer($log);
        if ($t === null || $t['removed'] || ! in_array($t['to'], $ours, true) || $t['raw_amount'] === '0') {
            return false;
        }
        $token = $this->registry->token($chainId, $t['contract']);
        if ($token === null) {
            return false;
        }
        $usd = Units::rawToUsd($t['raw_amount'], (int) $token->decimals, number_format((float) $token->peg_multiplier, 4, '.', ''));
        try {
            $dep = CryptoDeposit::create(['chain_id' => $chainId, 'tx_hash' => $t['tx_hash'], 'log_index' => $t['log_index'], 'block_number' => $t['block_number'], 'block_hash' => $t['block_hash'],
                'token_id' => $token->id, 'from_address' => $t['from'], 'to_address' => $t['to'], 'raw_amount' => $t['raw_amount'], 'dust_raw' => Units::dustRaw($t['raw_amount'], (int) $token->decimals),
                'usd_amount' => $usd, 'status' => DepositStatus::Detected->value, 'first_seen_at' => now()]);
        } catch (QueryException) {
            return false;   // already recorded by an earlier scan
        }
        CryptoDepositEvent::create(['deposit_id' => $dep->id, 'from_status' => null, 'to_status' => DepositStatus::Detected->value, 'actor' => 'system', 'note' => 'seen on chain', 'payload' => ['block' => $t['block_number']]]);

        return true;
    }
}
