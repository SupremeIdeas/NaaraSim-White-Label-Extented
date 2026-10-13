<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One completed experience that may be followed by a "how was it?" sheet. */
class ReviewMoment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['qualified_at' => 'datetime', 'shown_at' => 'datetime', 'stars' => 'integer', 'trustpilot_opened' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
