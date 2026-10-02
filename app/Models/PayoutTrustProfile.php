<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A user's payout trust tier (new | trusted | vip) — drives the Guardian's auto-approve limits. */
class PayoutTrustProfile extends Model
{
    public const NEW = 'new';

    public const TRUSTED = 'trusted';

    public const VIP = 'vip';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'tier', 'clean_payouts', 'last_incident_at', 'override_by', 'override_reason', 'updated_at'];

    protected function casts(): array
    {
        return ['last_incident_at' => 'datetime', 'updated_at' => 'datetime'];
    }
}
