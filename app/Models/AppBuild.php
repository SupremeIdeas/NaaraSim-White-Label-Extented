<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single native-app build request + its lifecycle (App Export §1).
 */
class AppBuild extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_BUILDING = 'building';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'platform', 'artifact_type', 'version', 'build_number',
        'status', 'artifact_url', 'release_notes', 'log', 'external_ref', 'triggered_by',
    ];

    protected function casts(): array
    {
        return ['build_number' => 'integer'];
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_FAILED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_BUILDING => 'Building',
            self::STATUS_READY => 'Ready (technical build only)',
            self::STATUS_FAILED => 'Failed',
            default => ucfirst($this->status),
        };
    }
}
