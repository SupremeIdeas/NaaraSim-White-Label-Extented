<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutReconciliationRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider', 'period_from', 'period_to', 'source', 'matched', 'flagged', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'created_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutReconciliationItem::class, 'run_id');
    }
}
