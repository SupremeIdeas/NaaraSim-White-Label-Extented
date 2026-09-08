<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * One append-only record of a white-label instance's call to the distribution
 * API (Batch 4 §2). No updated_at — this log is never mutated after write.
 */
class WhiteLabelApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'white_label_instance_id',
        'endpoint',
        'method',
        'response_status',
        'ip_address',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhiteLabelInstance::class, 'white_label_instance_id');
    }
}
