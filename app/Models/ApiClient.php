<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/**
 * A Developer API client (ROADMAP §Layer 2). It is the Sanctum *tokenable*, so
 * `auth:sanctum` resolves an ApiClient (not a User) on the developer API, and its
 * token abilities are the client's scopes. Billing runs off its own prepaid
 * balance. The owning developer is a relation.
 */
class ApiClient extends Model
{
    use HasApiTokens, HasFactory;

    /** The scopes a client may hold (Sanctum token abilities). */
    public const SCOPES = ['catalogue', 'quote', 'order', 'status'];

    protected $fillable = [
        'owner_user_id',
        'name',
        'token_last_four',
        'scopes',
        'rate_limit_tier',
        'prepaid_balance_usd',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'prepaid_balance_usd' => 'decimal:4',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** True when the client is active and its owner is still allowed to trade. */
    public function usable(): bool
    {
        return $this->is_active && $this->owner && $this->owner->is_active;
    }

    /** True if this client holds the given scope. */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
