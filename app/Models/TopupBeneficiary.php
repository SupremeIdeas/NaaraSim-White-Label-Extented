<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A saved recipient: a person's number with the network it is on. */
class TopupBeneficiary extends Model
{
    protected $fillable = ['user_id', 'name', 'phone_e164', 'country_iso', 'operator_name', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function autoReloads(): HasMany
    {
        return $this->hasMany(TopupAutoReload::class, 'beneficiary_id');
    }
}
