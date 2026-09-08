<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCharge extends Model
{
    protected $fillable = [
        'gateway', 'reference', 'provider_charge_id', 'amount', 'currency', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    /** Our NAARA reference for a provider charge id (dispute → reference mapping). */
    public static function referenceForChargeId(string $gateway, string $providerChargeId): ?string
    {
        if ($providerChargeId === '') {
            return null;
        }

        return static::query()
            ->where('gateway', $gateway)
            ->where('provider_charge_id', $providerChargeId)
            ->value('reference');
    }
}
