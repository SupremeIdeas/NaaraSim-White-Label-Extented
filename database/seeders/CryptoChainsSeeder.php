<?php

namespace Database\Seeders;

use App\Models\Crypto\CryptoChain;
use App\Models\Setting;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoToken;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Chains (all off until the install owner adds a receiving address and switches one on), issuer tokens and keyless public RPC endpoints. Re-running never edits a row an admin changed.
 * The chain ids are the well-known mainnet ids; the admin Test RPC button re-checks them against `eth_chainId` before anything is enabled.
 */
class CryptoChainsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('crypto_chains')) {
            return;
        }
        foreach ([
            [137, 'Polygon PoS', 'finalized', 128, 'https://polygonscan.com/tx/{hash}'],
            [1, 'Ethereum', 'finalized', 64, 'https://etherscan.io/tx/{hash}'],
            [56, 'BNB Smart Chain', 'depth', 20, 'https://bscscan.com/tx/{hash}'],
        ] as [$id, $name, $mode, $depth, $url]) {
            CryptoChain::firstOrCreate(['chain_id' => $id], ['name' => $name, 'finality_mode' => $mode, 'confirmations' => $depth, 'explorer_tx_url' => $url, 'enabled' => in_array($id, self::LIVE_CHAINS, true), 'credit_enabled' => in_array($id, self::LIVE_CHAINS, true), 'max_log_range' => 500]);
        }
        $this->seedOfficialDefaults();
        $this->seedPublicEndpoints();
        foreach (['crypto.enabled' => 0, 'crypto.credit_paused' => 0, 'crypto.require_secondary' => 1, 'crypto.link_nonce_ttl_minutes' => 10, 'crypto.auto_attribute_on_link' => 1,
            'crypto.unclaimed_max_age_days' => 30, 'crypto.retire_scan_days' => 45] as $k => $v) {
            if (Setting::getValue($k) === null) {
                Setting::setValue($k, $v, 'crypto');
            }
        }
    }

    /** Chains that ship ON. None: the install owner switches a network on after adding their own receiving address. */
    public const LIVE_CHAINS = [];

    /** Keyless public endpoints, two independent operators per chain so every verification is cross-checked. An admin can replace them with paid endpoints. Verified live 2026-11-15. */
    public const PUBLIC_RPC = [
        1 => ['primary' => 'https://ethereum-rpc.publicnode.com', 'secondary' => 'https://eth.drpc.org'],
        137 => ['primary' => 'https://polygon-bor-rpc.publicnode.com', 'secondary' => 'https://polygon.drpc.org'],
    ];

    public const OFFICIAL_LABEL = 'Receiving address';

    /** No receiving address ships: each install adds its OWN wallet in Admin, Crypto top-up. */
    public const OFFICIAL_ADDRESSES = [];

    /** Issuer-published contracts only (docs/crypto/RESEARCH-LOG.md C2a/C2b). BNB tokens are bridged, not issuer-published: they ship DISABLED until the owner signs the risk note. */
    private const TOKENS = [
        [1, 'USDC', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 6, true, 'Circle: https://developers.circle.com/stablecoins/usdc-contract-addresses'],
        [137, 'USDC', '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 6, true, 'Circle: https://developers.circle.com/stablecoins/usdc-contract-addresses (native USDC on Polygon PoS)'],
        [1, 'USDT', '0xdAC17F958D2ee523a2206206994597C13D831ec7', 6, true, 'Tether: https://tether.to/en/supported-protocols/'],
        [56, 'USDT', '0x55d398326f99059fF775485246999027B3197955', 18, false, 'Bridged (Binance-Peg) token: no issuer-published BSC contract. Disabled until the owner signs the risk note.'],
        [56, 'USDC', '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', 18, false, 'Bridged (Binance-Peg) token: no issuer-published BSC contract. Disabled until the owner signs the risk note.'],
    ];

    private function seedOfficialDefaults(): void
    {
        if (Schema::hasTable('crypto_deposit_addresses')) {
            foreach (self::OFFICIAL_ADDRESSES as $chainId => $addresses) {
                foreach ($addresses as $a) {
                    // firstOrCreate on the unique (chain, address): never edits a row an admin changed or retired.
                    CryptoDepositAddress::firstOrCreate(['chain_id' => $chainId, 'address' => strtolower($a)], ['label' => self::OFFICIAL_LABEL, 'status' => 'active', 'test_confirmed_at' => now()]);
                }
            }
        }
        if (Schema::hasTable('crypto_tokens')) {
            foreach (self::TOKENS as [$chainId, $symbol, $contract, $decimals, $on, $note]) {
                CryptoToken::firstOrCreate(['chain_id' => $chainId, 'contract' => strtolower($contract)], ['symbol' => $symbol, 'decimals' => $decimals, 'enabled' => $on, 'verified_source_note' => $note]);
            }
        }
    }

    private function seedPublicEndpoints(): void
    {
        if (! Schema::hasTable('crypto_rpc_endpoints')) {
            return;
        }
        foreach (self::PUBLIC_RPC as $chainId => $roles) {
            foreach ($roles as $role => $url) {
                if (! \App\Models\Crypto\CryptoRpcEndpoint::where(['chain_id' => $chainId, 'role' => $role])->exists()) {
                    \App\Models\Crypto\CryptoRpcEndpoint::create(['chain_id' => $chainId, 'role' => $role, 'url_encrypted' => $url, 'enabled' => true]);
                }
            }
        }
    }
}
