<?php

namespace App\Support\Appearance;

use App\Models\EsimOrder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The two-letter country the Passport skin dresses itself in (Prompt 20 §44). Order of truth:
 *   1. the destination of the member's most recently ACTIVE eSIM (EsimOrder.status = 'active' -> plan.countries, only when
 *      the plan covers exactly one country; a regional/global plan has no single country to show),
 *   2. the member's profile country (users.country_code),
 *   3. the platform default (config appearance.default_country).
 * Cached per member for 10 minutes. It must never block or fail a page render: any error returns the default.
 */
class ActiveCountryResolver
{
    public static function for(?User $user): string
    {
        $default = self::default();
        if ($user === null) {
            return $default;
        }

        try {
            return Cache::remember("appearance:country:{$user->id}", 600, fn () => self::lookup($user) ?? $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function forget(int $userId): void
    {
        Cache::forget("appearance:country:{$userId}");
    }

    private static function lookup(User $user): ?string
    {
        $order = EsimOrder::query()->with('plan:id,countries')
            ->where('user_id', $user->id)->where('status', 'active')
            ->orderByDesc('activated_at')->orderByDesc('id')->limit(10)->get()
            ->first(fn ($o) => count((array) $o->plan?->countries) === 1);

        $code = $order ? (string) array_values((array) $order->plan->countries)[0] : (string) $user->country_code;

        return self::clean($code);
    }

    private static function default(): string
    {
        return self::clean((string) config('appearance.default_country', 'ng')) ?? 'ng';
    }

    private static function clean(string $code): ?string
    {
        $code = strtolower(trim($code));

        return preg_match('/^[a-z]{2}$/', $code) ? $code : null;
    }
}
