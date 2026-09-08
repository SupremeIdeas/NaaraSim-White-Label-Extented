<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A per-audience user guide + agreement (admin-editable). */
class UserGuide extends Model
{
    protected $fillable = ['audience', 'title', 'intro', 'sections', 'agreement'];

    protected function casts(): array
    {
        return ['sections' => 'array'];
    }
}
