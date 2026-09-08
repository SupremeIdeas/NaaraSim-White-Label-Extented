<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One-time video-watch claim (BUILD-9 §2.6). */
class BrandVideoWatchClaim extends Model
{
    protected $fillable = ['user_id', 'video_id', 'watched_at'];

    protected $casts = ['watched_at' => 'datetime'];
}
