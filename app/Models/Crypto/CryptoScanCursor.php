<?php

namespace App\Models\Crypto;

use Illuminate\Database\Eloquent\Model;

class CryptoScanCursor extends Model
{
    protected $table = 'crypto_scan_cursors';

    public $timestamps = false;

    protected $fillable = ['chain_id', 'last_scanned_block', 'last_head', 'lag_blocks', 'updated_at'];

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }
}
