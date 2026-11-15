<?php

namespace App\Models\Crypto;

use Illuminate\Database\Eloquent\Model;

/** Append-only history of a deposit. Updating or deleting a row throws: corrections are new events, never edits. */
class CryptoDepositEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'crypto_deposit_events';

    protected $fillable = ['deposit_id', 'from_status', 'to_status', 'actor', 'note', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Deposit events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Deposit events are append-only.'));
    }
}
