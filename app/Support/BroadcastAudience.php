<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audience targeting for the Email Studio broadcast tool (NAARA-BUILD-20 §3.2).
 * Reuses the existing role / merchant / partner distinctions — no second
 * targeting system. Returns a User query so the count and the send both run off
 * the exact same definition.
 */
class BroadcastAudience
{
    /** @return array<string, string> type => label for the picker. */
    public static function types(): array
    {
        return [
            'all' => 'All users',
            'role' => 'By role',
            'account' => 'By account type',
            'segment' => 'Segment',
        ];
    }

    public static function roles(): array
    {
        return ['user' => 'Customers', 'staff' => 'Staff', 'admin' => 'Admins', 'super_admin' => 'Super admins'];
    }

    public static function accounts(): array
    {
        return ['customer' => 'Customers (no merchant/partner)', 'merchant_v1' => 'Merchant V1', 'merchant_v2' => 'Merchant V2', 'partner' => 'Partners'];
    }

    public static function segments(): array
    {
        return ['active' => 'Active accounts', 'unverified' => 'Unverified email'];
    }

    /** Build the User query for a given audience. Active + non-deleted only. */
    public static function query(string $type, ?string $value): Builder
    {
        $q = User::query()->where('is_active', true);

        return match ($type) {
            'role' => $q->when(array_key_exists((string) $value, self::roles()), fn ($qq) => $qq->role($value), fn ($qq) => $qq->whereRaw('1=0')),
            'account' => self::accountScope($q, (string) $value),
            'segment' => self::segmentScope($q, (string) $value),
            default => $q, // 'all'
        };
    }

    private static function accountScope(Builder $q, string $value): Builder
    {
        return match ($value) {
            'partner' => $q->whereHas('partnerAccount'),
            'merchant_v1' => $q->whereHas('merchantAccount', fn ($m) => $m->where('status', 'active')->where('tier', '!=', 'v2')),
            'merchant_v2' => $q->whereHas('merchantAccount', fn ($m) => $m->where('status', 'active')->where('tier', 'v2')),
            'customer' => $q->whereDoesntHave('merchantAccount')->whereDoesntHave('partnerAccount'),
            default => $q->whereRaw('1=0'),
        };
    }

    private static function segmentScope(Builder $q, string $value): Builder
    {
        return match ($value) {
            'unverified' => $q->whereNull('email_verified_at'),
            'active' => $q,
            default => $q->whereRaw('1=0'),
        };
    }

    /** Human label for a resolved audience (for history + confirmation). */
    public static function label(string $type, ?string $value): string
    {
        return match ($type) {
            'role' => (self::roles()[$value] ?? $value).' (role)',
            'account' => self::accounts()[$value] ?? $value,
            'segment' => self::segments()[$value] ?? $value,
            default => 'All users',
        };
    }

    public static function count(string $type, ?string $value): int
    {
        try {
            return self::query($type, $value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
