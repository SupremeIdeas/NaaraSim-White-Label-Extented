<?php

namespace App\Services\Crypto\Health;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoRpcEndpoint;
use App\Models\Crypto\CryptoScanCursor;
use App\Models\Crypto\CryptoToken;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\Crypto\Chain\AbiDecoder;
use App\Services\Crypto\Chain\RpcClient;
use App\Services\Crypto\Chain\Units;
use App\Services\Crypto\Link\SignatureVerifier;
use App\Services\Crypto\Support\CanonicalAssets;
use App\Services\Crypto\Support\DepositStatus;
use App\Services\Crypto\Support\MathExtensionManager;
use App\Support\CronPulse;
use Illuminate\Support\Facades\Cache;

/**
 * Proves, on demand and on a schedule, that the crypto top-up is genuine and healthy: the code and libraries still do their maths correctly (known test vectors), the
 * configuration still matches the issuers' official contracts, every endpoint answers the right chain with a fresh block, the scanner keeps up, nothing is stuck, and the
 * wallet ledger matches the deposit table to the cent. Each check returns ok / warn / fail with a plain sentence. Read-only: it never moves money or edits a row.
 * `offline()` touches no network (safe in tests, Verify and every page load); `run()` adds the live endpoint checks.
 */
class CryptoSelfTest
{
    public const CACHE = 'crypto.selftest.last';

    /** A signature made with a throw-away test key over a fixed sentence: the verifier must recover exactly this address. */
    private const VECTOR_MESSAGE = 'Self-test vector 1';
    private const VECTOR_SIG = '0x3c162cbb25ccf46b1082a0f849c7757ea7cd3f7cd3ea60f05dfe8c18318fc74a3cd0238a11e7f13ec7134641864e983017e5b4eca83b5b62340f099546f32d7d1c';
    private const VECTOR_ADDR = '0x1563915e194d8cfba1943570603f7606a3115508';

    /** @return list<array{key: string, title: string, status: 'ok'|'warn'|'fail', detail: string}> */
    public function offline(): array
    {
        return array_values(array_filter([
            $this->maths(), $this->signatures(), $this->contracts(), $this->addresses(), $this->scanner(), $this->stuck(), $this->ledger(), $this->scheduler(), $this->switches(),
        ]));
    }

    /** Offline checks plus one live read of every enabled endpoint. Stores the result for the admin page and returns it. @return list<array<string, string>> */
    public function run(): array
    {
        $all = [...$this->offline(), ...$this->endpoints()];
        Cache::forever(self::CACHE, ['at' => now()->toIso8601String(), 'checks' => $all]);

        return $all;
    }

    /** @return array{at: string, checks: list<array<string, string>>}|null */
    public static function last(): ?array
    {
        $v = Cache::get(self::CACHE);

        return is_array($v) ? $v : null;
    }

    public static function worst(array $checks): string
    {
        $s = array_column($checks, 'status');

        return in_array('fail', $s, true) ? 'fail' : (in_array('warn', $s, true) ? 'warn' : 'ok');
    }

    private function row(string $key, string $title, string $status, string $detail): array
    {
        return ['key' => $key, 'title' => $title, 'status' => $status, 'detail' => $detail];
    }

    private function maths(): array
    {
        $t = 'Amount maths and log decoding are correct';
        $topic = class_exists(\kornrunner\Keccak::class) ? '0x'.\kornrunner\Keccak::hash('Transfer(address,address,uint256)', 256) : null;
        if ($topic !== null && $topic !== AbiDecoder::TRANSFER_TOPIC) {
            return $this->row('maths', $t, 'fail', 'The Transfer event signature does not match its keccak hash. The decoder must not be trusted.');
        }
        $ok = Units::rawToUsd('12500000', 6) === '12.5000' && Units::rawToUsd('1', 6) === '0.0000' && Units::rawToUsd('999999999999999999999', 18, '1.0000') === '999.9999' && Units::hexToDec('0xde0b6b3a7640000') === '1000000000000000000';
        $log = ['address' => '0x'.str_repeat('a', 40), 'topics' => [AbiDecoder::TRANSFER_TOPIC, '0x'.str_repeat('0', 24).str_repeat('1', 40), '0x'.str_repeat('0', 24).str_repeat('2', 40)], 'data' => '0x'.str_pad('2faf080', 64, '0', STR_PAD_LEFT),
            'transactionHash' => '0x'.str_repeat('b', 64), 'blockHash' => '0x'.str_repeat('c', 64), 'blockNumber' => '0x10', 'logIndex' => '0x3'];
        $d = AbiDecoder::decodeTransfer($log);
        $ok = $ok && $d !== null && $d['raw_amount'] === '50000000' && $d['log_index'] === 3 && $d['to'] === '0x'.str_repeat('2', 40);

        return $ok ? $this->row('maths', $t, 'ok', 'Known amounts and a sample transfer decode to the exact expected values.') : $this->row('maths', $t, 'fail', 'A known test amount or transfer decoded wrongly. Do not credit anything until this is fixed.');
    }

    private function signatures(): array
    {
        $t = 'Wallet signature check works';
        if (! SignatureVerifier::available()) {
            return $this->row('signatures', $t, 'warn', 'The gmp or bcmath extension is missing, so members cannot verify a wallet. Install it in Admin, Crypto top-up.');
        }

        return SignatureVerifier::recover(self::VECTOR_MESSAGE, self::VECTOR_SIG) === self::VECTOR_ADDR
            ? $this->row('signatures', $t, 'ok', 'A known signed test message recovers exactly the right wallet address.')
            : $this->row('signatures', $t, 'fail', 'A known test signature no longer verifies. Wallet linking cannot be trusted.');
    }

    private function contracts(): array
    {
        $t = 'Accepted tokens are the issuers\' official contracts';
        $bad = [];
        foreach (CryptoToken::where('enabled', true)->get() as $tok) {
            if (! CanonicalAssets::matches((int) $tok->chain_id, (string) $tok->symbol, (string) $tok->contract)) {
                $bad[] = $tok->symbol.' on chain '.$tok->chain_id.' ('.substr((string) $tok->contract, 0, 10).'…)';
            }
            if ((float) $tok->peg_multiplier != 1.0) {
                $bad[] = $tok->symbol.' on chain '.$tok->chain_id.' has a peg of '.$tok->peg_multiplier.', not 1.0';
            }
        }

        return $bad === [] ? $this->row('contracts', $t, 'ok', 'Every enabled token matches the issuer-published contract and is credited one for one.')
            : $this->row('contracts', $t, 'fail', 'Not the official contract or not 1:1: '.implode('; ', $bad).'. Disable it now in Admin, Crypto top-up.');
    }

    private function addresses(): array
    {
        $t = 'Receiving addresses are in place';
        $live = CryptoChain::where('enabled', true)->where('credit_enabled', true)->pluck('chain_id');
        $missing = $live->filter(fn ($c) => ! CryptoDepositAddress::where('chain_id', $c)->where('status', 'active')->exists());
        if ($missing->isNotEmpty()) {
            return $this->row('addresses', $t, 'fail', 'No active receiving address on chain '.$missing->implode(', ').'. Members cannot pay there.');
        }
        $custom = CryptoDepositAddress::where('status', 'active')->get()->filter(fn ($a) => ! in_array(strtolower($a->address), self::officialAddresses((int) $a->chain_id), true));
        if (self::officialAddresses(1) !== [] && $custom->isNotEmpty()) {
            return $this->row('addresses', $t, 'warn', $custom->count().' active address(es) are not the official defaults ('.$custom->map(fn ($a) => substr($a->address, 0, 8).'…'.substr($a->address, -4))->implode(', ').'). Fine if you added them; if not, retire them and check the audit log.');
        }

        return $this->row('addresses', $t, 'ok', 'Each live network has an active receiving address'.(self::officialAddresses(1) !== [] ? ' and they are the official defaults.' : '.'));
    }

    /** @return list<string> */
    private static function officialAddresses(int $chainId): array
    {
        return class_exists(\Database\Seeders\CryptoChainsSeeder::class) ? array_map('strtolower', \Database\Seeders\CryptoChainsSeeder::OFFICIAL_ADDRESSES[$chainId] ?? []) : [];
    }

    private function scanner(): array
    {
        $t = 'The scanner is keeping up';
        if (! (bool) Setting::getValue('crypto.enabled', 0)) {
            return $this->row('scanner', $t, 'ok', 'Crypto top-up is switched off, so nothing needs scanning.');
        }
        $stale = [];
        foreach (CryptoChain::where('enabled', true)->get() as $c) {
            $cur = CryptoScanCursor::where('chain_id', $c->chain_id)->first();
            if ($cur === null || $cur->updated_at === null) {
                $stale[] = $c->name.' has never been scanned';
            } elseif ($cur->updated_at->lt(now()->subMinutes(15))) {
                $stale[] = $c->name.' was last scanned '.$cur->updated_at->diffForHumans();
            } elseif ((int) $cur->lag_blocks > max(200, (int) $c->max_log_range * 2)) {
                $stale[] = $c->name.' is '.$cur->lag_blocks.' blocks behind';
            }
        }

        return $stale === [] ? $this->row('scanner', $t, 'ok', 'Every watched network was scanned in the last 15 minutes and is at the chain head.') : $this->row('scanner', $t, 'fail', implode('; ', $stale).'. Check the scheduler and the endpoints.');
    }

    private function stuck(): array
    {
        $t = 'No deposit is stuck';
        $pending = CryptoDeposit::where('status', DepositStatus::CreditPending->value)->where('updated_at', '<', now()->subMinutes(10))->count();
        $slow = CryptoDeposit::whereIn('status', [DepositStatus::Detected->value, DepositStatus::Confirming->value])->where('created_at', '<', now()->subHours(2))->count();
        $held = CryptoDeposit::where('status', DepositStatus::HeldReview->value)->where('updated_at', '<', now()->subDay())->count();
        if ($pending > 0) {
            return $this->row('stuck', $t, 'fail', $pending.' verified deposit(s) have waited over 10 minutes to be credited. Members have paid and not been credited: look at the alerts and the log now.');
        }
        if ($slow > 0 || $held > 0) {
            return $this->row('stuck', $t, 'warn', trim(($slow ? $slow.' deposit(s) unconfirmed for over 2 hours. ' : '').($held ? $held.' held for review over a day.' : '')));
        }

        return $this->row('stuck', $t, 'ok', 'Nothing is waiting longer than it should.');
    }

    private function ledger(): array
    {
        $t = 'Wallet ledger matches the deposits to the cent';
        $credited = CryptoDeposit::where('status', DepositStatus::Credited->value)->get(['id', 'credit_reference', 'usd_amount', 'credited_wallet_txn_id']);
        $bad = 0;
        $byRef = WalletTransaction::whereIn('reference', $credited->pluck('credit_reference')->filter()->all())->get()->keyBy('reference');
        foreach ($credited as $d) {
            $tx = $byRef[$d->credit_reference] ?? null;
            if ($tx === null || abs((float) $tx->amount - (float) $d->usd_amount) > 0.00011 || $tx->type !== 'credit') {
                $bad++;
            }
        }
        $known = $credited->pluck('credit_reference')->filter()->all();
        $orphans = WalletTransaction::where('reference', 'like', 'topup:crypto:%')->whereNotIn('reference', $known ?: ['-'])->count();
        if ($bad > 0 || $orphans > 0) {
            return $this->row('ledger', $t, 'fail', ($bad ? $bad.' credited deposit(s) have no matching wallet credit or the wrong amount. ' : '').($orphans ? $orphans.' wallet credit(s) say "crypto" but have no deposit behind them.' : '').' Stop crediting and investigate.');
        }

        return $this->row('ledger', $t, 'ok', $credited->count().' credited deposit(s) each match exactly one wallet credit, and no crypto credit exists without a deposit.');
    }

    private function scheduler(): array
    {
        $t = 'The scheduler is running';
        $s = CronPulse::pulse()['state'];

        return $s === 'live' ? $this->row('scheduler', $t, 'ok', 'The cron fired within the last two minutes, so the scanner is being run.')
            : $this->row('scheduler', $t, $s === 'waiting' ? 'warn' : 'fail', 'The cron has not been seen recently, so deposits are not being scanned. Fix it in Admin, System health.');
    }

    private function switches(): array
    {
        $t = 'Crediting is not paused';

        return (bool) Setting::getValue('crypto.credit_paused', 0) ? $this->row('switches', $t, 'warn', 'All crediting is paused. Deposits are recorded but nobody is credited until you unpause.') : $this->row('switches', $t, 'ok', 'Crediting is active.');
    }

    /** One live read per enabled endpoint: right chain, a fresh block, a readable finalized block. @return list<array{key: string, title: string, status: string, detail: string}> */
    private function endpoints(): array
    {
        $out = [];
        foreach (CryptoChain::where('enabled', true)->get() as $c) {
            $eps = CryptoRpcEndpoint::where('chain_id', $c->chain_id)->where('enabled', true)->get();
            $good = 0;
            $notes = [];
            foreach ($eps as $ep) {
                try {
                    $cl = new RpcClient((string) $ep->url_encrypted, 150, 1);
                    $id = $cl->chainId();
                    $head = $cl->getBlockByNumber('latest');
                    $age = now()->timestamp - (int) Units::hexToDec((string) ($head['timestamp'] ?? '0x0'));
                    $fin = $c->finality_mode === 'finalized' ? $cl->getBlockByNumber('finalized') : $head;
                    if ($id !== (int) $c->chain_id) {
                        $notes[] = $ep->maskedUrl().' answers chain '.$id;
                    } elseif ($age > 300) {
                        $notes[] = $ep->maskedUrl().' is '.$age.'s behind';
                    } elseif ($fin === null) {
                        $notes[] = $ep->maskedUrl().' cannot read finalized blocks';
                    } else {
                        $good++;
                    }
                } catch (\Throwable $e) {
                    $notes[] = $ep->maskedUrl().' did not answer';
                }
            }
            $title = $c->name.': RPC endpoints';
            $key = 'rpc_'.$c->chain_id;
            if ($good === 0) {
                $out[] = $this->row($key, $title, 'fail', 'No endpoint is usable ('.implode('; ', $notes ?: ['none configured']).'). Deposits cannot be read or verified.');
            } elseif ($good < 2) {
                $out[] = $this->row($key, $title, 'warn', 'Only '.$good.' endpoint is healthy'.($notes ? ' ('.implode('; ', $notes).')' : '').', so deposits cannot be cross-checked. Add or fix a second one.');
            } else {
                $out[] = $this->row($key, $title, 'ok', $good.' independent endpoints answer the right chain with a fresh block.');
            }
        }

        return $out;
    }
}
