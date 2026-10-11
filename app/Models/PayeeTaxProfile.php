<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tax-reporting hook for payees (Addendum D-3.18). Engineering only holds status; the owner's accountant decides what is required. */
class PayeeTaxProfile extends Model
{
    protected $fillable = ['user_id', 'tax_country', 'form_type', 'form_status', 'collected_at', 'provider_collected'];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'provider_collected' => 'boolean'];
    }
}
