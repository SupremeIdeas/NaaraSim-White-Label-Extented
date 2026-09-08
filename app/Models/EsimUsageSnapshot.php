<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One point-in-time usage reading for an eSIM order (Connectivity Analytics
 * blueprint Part A §2.2.1). Written by CaptureEsimUsageSnapshotJob, read by
 * ConnectivityAnalyticsService — never written to directly from a Livewire
 * component or controller.
 */
class EsimUsageSnapshot extends Model
{
    protected $fillable = [
        'esim_order_id',
        'user_id',
        'data_total_mb',
        'data_remaining_mb',
        'data_used_mb',
        'bundle_status',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'data_total_mb' => 'integer',
            'data_remaining_mb' => 'integer',
            'data_used_mb' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EsimOrder::class, 'esim_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
