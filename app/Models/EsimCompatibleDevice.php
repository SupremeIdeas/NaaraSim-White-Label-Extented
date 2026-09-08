<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EsimCompatibleDevice extends Model
{
    protected $fillable = ['brand', 'os_group', 'category', 'device_name'];
}
