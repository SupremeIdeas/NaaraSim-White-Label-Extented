<?php

namespace App\Models\Crypto;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A sender wallet a member proved they own by signing. One address belongs to one member at a time (unique active_key). */
class CryptoLinkedWallet extends Model
{
    protected $table = 'crypto_linked_wallets';

    protected $fillable = ['user_id', 'family', 'address', 'active_key', 'verified_at', 'revoked_at', 'last_nonce_id', 'ip_hash', 'ua_hash'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $w) {
            $w->address = strtolower((string) $w->address);
            if ($w->revoked_at === null && $w->active_key === null) {
                $w->active_key = '1';
            }
        });
    }

    public function scopeActive($q)
    {
        return $q->whereNotNull('verified_at')->whereNull('revoked_at');
    }

    /** Soft revoke: the active key becomes r plus the row id (never the bare '1' an active row uses), so the address can be linked again (to anyone, after the cooldown the service enforces). */
    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now(), 'active_key' => 'r'.$this->id])->save();
    }
}
