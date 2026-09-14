<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared-wallet / family plan primitive (Prompt 11 §3). A group's spending
 * always debits the OWNER's own UserWallet — this table holds no balance of
 * its own, only who may spend from the owner's wallet and up to what cap.
 */
class WalletGroup extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WalletGroupMember::class);
    }
}
