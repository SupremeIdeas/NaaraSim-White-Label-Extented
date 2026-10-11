<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutWithdrawalStatHourly extends Model
{
    protected $table = 'payout_withdrawal_stats_hourly';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['hour_start' => 'datetime'];
    }
}
