<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to apply a `.naaraupdate` package (Batch 2 §3). Status moves
 * pending → applying → (succeeded | rolled_back | failed_unrecoverable).
 */
class PlatformUpdateAttempt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLYING = 'applying';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    /** The one true worst case: the apply failed AND the rollback itself failed. */
    public const STATUS_FAILED_UNRECOVERABLE = 'failed_unrecoverable';

    protected $fillable = [
        'package_id',
        'from_version',
        'to_version',
        'status',
        'backup_archive_path',
        'files_changed_count',
        'migrations_run_count',
        'downtime_seconds',
        'health_check_result',
        'failure_reason',
        'initiated_by_user_id',
        'maintenance_started_at',
        'maintenance_ended_at',
    ];

    protected $casts = [
        'health_check_result' => 'array',
        'maintenance_started_at' => 'datetime',
        'maintenance_ended_at' => 'datetime',
    ];

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCEEDED,
            self::STATUS_ROLLED_BACK,
            self::STATUS_FAILED_UNRECOVERABLE,
        ], true);
    }
}
