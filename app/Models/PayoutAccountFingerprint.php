<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HMAC fingerprint of a payout destination — detects one account shared by several users. */
class PayoutAccountFingerprint extends Model
{
    protected $fillable = ['provider', 'fingerprint', 'user_id', 'payout_account_id'];
}
