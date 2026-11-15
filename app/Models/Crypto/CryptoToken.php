<?php

namespace App\Models\Crypto;

use Illuminate\Database\Eloquent\Model;

/** An allow-listed token contract. The contract, decimals and issuer source come from an admin, never from a request or the chain feed. */
class CryptoToken extends Model
{
    protected $table = 'crypto_tokens';

    protected $fillable = ['chain_id', 'symbol', 'contract', 'decimals', 'peg_multiplier', 'min_usd', 'max_single_usd', 'user_daily_cap_usd', 'review_above_usd', 'enabled', 'verified_by_admin_at', 'verified_source_note'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'decimals' => 'integer', 'verified_by_admin_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $t) {
            $t->contract = strtolower((string) $t->contract);
            // A token cannot be switched on without the issuer-page URL that proves the contract (blueprint 8, tab 3).
            if ($t->enabled && ! preg_match('#https?://#i', (string) $t->verified_source_note)) {
                throw new \DomainException('A token can only be enabled with the issuer page URL in its source note.');
            }
        });
    }
}
