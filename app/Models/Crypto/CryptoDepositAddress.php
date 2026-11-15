<?php

namespace App\Models\Crypto;

use Illuminate\Database\Eloquent\Model;

/** A receive-only deposit address. No private key is ever stored or used here. */
class CryptoDepositAddress extends Model
{
    protected $table = 'crypto_deposit_addresses';

    protected $fillable = ['chain_id', 'address', 'label', 'status', 'active_from_block', 'proposed_by', 'approved_by', 'activate_after', 'test_confirmed_at', 'fingerprint'];

    protected function casts(): array
    {
        return ['activate_after' => 'datetime', 'test_confirmed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $a) {
            $a->address = strtolower((string) $a->address);
            $a->fingerprint = hash('sha256', $a->chain_id.'|'.$a->address);
        });
    }

    /** Active and retiring addresses keep being scanned, so a late payment to a retired address is never lost. */
    public function scopeScanned($q)
    {
        return $q->whereIn('status', ['active', 'retiring']);
    }
}
