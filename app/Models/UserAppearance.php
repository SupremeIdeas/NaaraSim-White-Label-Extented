<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One member's own skin / accent / mode / dials. Null column = follow the platform default. */
class UserAppearance extends Model
{
    public const DIALS = ['round', 'dens', 'ts', 'depth', 'font', 'motion'];

    protected $table = 'user_appearance';

    protected $fillable = ['user_id', 'skin_key', 'accent_key', 'accent_hex', 'mode', 'round', 'dens', 'ts', 'depth', 'font', 'motion', 'last_free_skin_key', 'last_pro_skin_key', 'last_pro_accent_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
