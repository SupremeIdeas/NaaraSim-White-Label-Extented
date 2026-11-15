<?php

namespace App\Models\Crypto;

use Illuminate\Database\Eloquent\Model;

/** A single-use, expiring challenge for linking a wallet. The server rebuilds the message from its own stored copy; the client's text is never trusted. */
class CryptoLinkNonce extends Model
{
    protected $table = 'crypto_link_nonces';

    protected $fillable = ['user_id', 'nonce', 'address', 'chain_id', 'message_hash', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    /** Atomically consume: exactly one caller wins, a replay gets false. */
    public function consume(): bool
    {
        return static::query()->whereKey($this->getKey())->whereNull('consumed_at')->where('expires_at', '>', now())->update(['consumed_at' => now()]) === 1;
    }
}
