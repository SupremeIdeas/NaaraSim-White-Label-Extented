<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A user's confirmed understanding of the global rail's terms (slower, own verified name, own account). */
class PayoutRailAcknowledgement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'rail', 'country', 'guide_version', 'ip_hash', 'user_agent_hash'];
}
