<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A posted status-page incident + its update timeline (Status page §1). */
class Incident extends Model
{
    public const STATUSES = ['investigating', 'identified', 'monitoring', 'resolved'];
    public const IMPACTS = ['minor', 'major', 'critical', 'maintenance'];

    protected $fillable = [
        'title', 'component', 'impact', 'status', 'started_at', 'resolved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function updates()
    {
        return $this->hasMany(IncidentUpdate::class)->latest('id');
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
