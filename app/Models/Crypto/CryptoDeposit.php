<?php

namespace App\Models\Crypto;

use App\Services\Crypto\Support\DepositStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A transfer seen on chain. Identity is (chain_id, tx_hash, log_index), unique in the database. Status changes go through transition() only: it locks the
 * row, checks the whitelist in DepositStatus and writes one CryptoDepositEvent in the same transaction (invariant I9).
 */
class CryptoDeposit extends Model
{
    protected $table = 'crypto_deposits';

    protected $fillable = ['chain_id', 'tx_hash', 'log_index', 'block_number', 'block_hash', 'token_id', 'from_address', 'to_address', 'raw_amount', 'dust_raw', 'usd_amount',
        'status', 'attributed_user_id', 'attributed_at', 'credited_wallet_txn_id', 'credit_reference', 'first_seen_at', 'verified_at', 'credited_at', 'hold_reason'];

    protected function casts(): array
    {
        return ['attributed_at' => 'datetime', 'first_seen_at' => 'datetime', 'verified_at' => 'datetime', 'credited_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $d) {
            foreach (['tx_hash', 'block_hash', 'from_address', 'to_address'] as $k) {
                $d->{$k} = strtolower((string) $d->{$k});
            }
        });
    }

    public function token(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CryptoToken::class, 'token_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CryptoDepositEvent::class, 'deposit_id');
    }

    public function state(): DepositStatus
    {
        return DepositStatus::from((string) $this->status);
    }

    /** The wallet reference is derived ONLY from the chain event (invariant I1); no request input ever touches it. */
    public static function referenceFor(int $chainId, string $txHash, int $logIndex): string
    {
        return 'topup:crypto:'.$chainId.':'.strtolower($txHash).':'.$logIndex;
    }

    /**
     * Move to another status under a row lock, or throw. Returns the refreshed deposit.
     *
     * @param  array<string, mixed>  $set  extra columns to write with the transition
     */
    public function transition(DepositStatus $to, string $actor = 'system', ?string $note = null, array $payload = [], array $set = []): self
    {
        return DB::transaction(function () use ($to, $actor, $note, $payload, $set) {
            $row = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $from = $row->state();
            if (! $from->canTransitionTo($to)) {
                throw new \DomainException("Deposit {$row->id}: {$from->value} cannot move to {$to->value}.");
            }
            $row->forceFill($set + ['status' => $to->value])->save();
            CryptoDepositEvent::create(['deposit_id' => $row->id, 'from_status' => $from->value, 'to_status' => $to->value, 'actor' => $actor, 'note' => $note, 'payload' => $payload ?: null]);

            return $row->refresh();
        });
    }

    /** Set the owner once (invariant I3): refused if already set to someone else or once the credit is pending. */
    public function attributeTo(int $userId): self
    {
        return DB::transaction(function () use ($userId) {
            $row = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if ($row->attributed_user_id !== null && (int) $row->attributed_user_id !== $userId) {
                throw new \DomainException('This deposit is already attributed to another member.');
            }
            if ($row->state()->freezesAttribution() && (int) $row->attributed_user_id !== $userId) {
                throw new \DomainException('Attribution is frozen once the credit is pending.');
            }
            if ($row->attributed_user_id === null) {
                $row->forceFill(['attributed_user_id' => $userId, 'attributed_at' => now()])->save();
                CryptoDepositEvent::create(['deposit_id' => $row->id, 'from_status' => $row->status, 'to_status' => $row->status, 'actor' => 'system', 'note' => 'attributed']);
            }

            return $row->refresh();
        });
    }
}
