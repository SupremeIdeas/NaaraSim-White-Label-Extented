<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Claude-proposed fix for a logged error (blueprint Section 29).
 */
class MaintenanceProposal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PR_OPENED = 'pr_opened';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'error_log_id',
        'title',
        'summary',
        'diff',
        'changes',
        'status',
        'branch',
        'pr_url',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function errorLog(): BelongsTo
    {
        return $this->belongsTo(ErrorLog::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
