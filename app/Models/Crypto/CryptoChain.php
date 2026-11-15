<?php

namespace App\Models\Crypto;

use Illuminate\Database\Eloquent\Model;

/** An EVM chain we may watch. Disabled until an admin turns it on; credit is a second, separate switch. */
class CryptoChain extends Model
{
    protected $table = 'crypto_chains';

    protected $fillable = ['chain_id', 'name', 'family', 'confirmations', 'finality_mode', 'max_log_range', 'enabled', 'credit_enabled', 'explorer_tx_url'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'credit_enabled' => 'boolean', 'chain_id' => 'integer', 'confirmations' => 'integer', 'max_log_range' => 'integer'];
    }
}
