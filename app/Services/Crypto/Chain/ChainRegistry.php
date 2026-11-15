<?php

namespace App\Services\Crypto\Chain;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoRpcEndpoint;
use App\Models\Crypto\CryptoToken;
use App\Models\Setting;

/**
 * The one place that answers "what are we allowed to watch and credit". Everything comes from the admin-managed tables (chain, token allow-list, deposit addresses),
 * never from a request or from the chain feed. Kill switches are explicit: the global feature flag, the global credit pause, and per-chain enabled / credit_enabled.
 */
class ChainRegistry
{
    public function featureOn(): bool
    {
        return (bool) Setting::getValue('crypto.enabled', 0);
    }

    public function creditPaused(): bool
    {
        return (bool) Setting::getValue('crypto.credit_paused', 0);
    }

    /** May we WATCH this chain (record deposits)? */
    public function watching(int $chainId): bool
    {
        $c = CryptoChain::where('chain_id', $chainId)->first();

        return $this->featureOn() && $c !== null && $c->enabled;
    }

    /** May we CREDIT on this chain? Needs the feature on, no global pause, the chain enabled and its own credit switch on. */
    public function crediting(int $chainId): bool
    {
        $c = CryptoChain::where('chain_id', $chainId)->first();

        return $this->watching($chainId) && ! $this->creditPaused() && $c->credit_enabled;
    }

    /** @return \Illuminate\Support\Collection<int, CryptoChain> */
    public function enabledChains()
    {
        return $this->featureOn() ? CryptoChain::where('enabled', true)->orderBy('chain_id')->get() : collect();
    }

    /** Allow-listed token by chain and EMITTING contract (case-insensitive). Disabled tokens are returned too: their deposits are recorded as paused_hold. */
    public function token(int $chainId, string $contract): ?CryptoToken
    {
        return CryptoToken::where('chain_id', $chainId)->where('contract', strtolower($contract))->first();
    }

    /** @return \Illuminate\Support\Collection<int, CryptoToken> */
    public function tokens(int $chainId)
    {
        return CryptoToken::where('chain_id', $chainId)->get();
    }

    /** Addresses still scanned for this chain (active and retiring), lower-case. @return list<string> */
    public function scannedAddresses(int $chainId): array
    {
        return CryptoDepositAddress::where('chain_id', $chainId)->scanned()->pluck('address')->map('strtolower')->all();
    }

    public function isOurAddress(int $chainId, string $address): bool
    {
        return in_array(strtolower($address), $this->scannedAddresses($chainId), true);
    }

    /** The RPC pool for a chain, or null when no primary endpoint is configured. */
    public function pool(int $chainId): ?RpcPool
    {
        $eps = CryptoRpcEndpoint::where('chain_id', $chainId)->where('enabled', true)->get()->keyBy('role');
        if (! isset($eps['primary'])) {
            return null;
        }

        return new RpcPool($chainId, new RpcClient((string) $eps['primary']->url_encrypted), isset($eps['secondary']) ? new RpcClient((string) $eps['secondary']->url_encrypted) : null);
    }
}
