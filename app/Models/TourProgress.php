<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourProgress extends Model
{
    protected $table = 'tour_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
