<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutCorridor;
use App\Models\PayoutCountryRail;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The single source of truth for country support by rail (Rail Guide §2).
 *
 * Cell states, per country + rail:
 *   available     provider covers it AND we have it switched on (an enabled corridor, or a force_on override)
 *   coming_soon   provider covers it but we have not enabled it — NEVER shown as a tick
 *   not_supported the provider does not cover the country (or force_off)
 *   blocked       the country is on the payout deny list (beats everything)
 *
 * `we_enabled` is derived from the corridor table at read time, so the guide can only
 * ever promise what the router can actually do.
 */
class PayoutRailRegistry
{
    public const RAILS = ['paystack', 'flutterwave', 'stripe_connect', 'paypal', 'cryptomus', 'global'];

    /** The four rails shown as columns in the country matrix. */
    public const MATRIX_RAILS = ['paystack', 'flutterwave', 'stripe_connect', 'global'];

    private const VERSION_KEY = 'payout:rails:version';

    /** Which corridor providers make up each rail. */
    public static function providersFor(string $rail): array
    {
        return match ($rail) {
            'stripe_connect' => ['stripe'],
            'global' => RailEnrollmentService::globalProviders(),
            default => [$rail],
        };
    }

    public static function railForProvider(?string $provider): ?string
    {
        return match (true) {
            $provider === null => null,
            $provider === 'stripe' => 'stripe_connect',
            RailEnrollmentService::isGlobal($provider) => 'global',
            default => $provider,
        };
    }

    /** True once the registry has been seeded; before that callers keep their legacy constants. */
    public function seeded(): bool
    {
        return Schema::hasTable('payout_country_rails') && PayoutCountryRail::query()->exists();
    }

    /** Does the PROVIDER cover this country on this rail (override-aware)? Used by the bank resolvers. */
    public function providerCovers(string $country, string $rail): bool
    {
        $row = $this->rows()->get(strtoupper($country).'|'.$rail);
        if ($row === null) {
            return false;
        }

        return match ($row->admin_override) {
            'force_off' => false,
            'force_on' => true,
            default => (bool) $row->provider_supports,
        };
    }

    /** @return 'available'|'coming_soon'|'not_supported'|'blocked' */
    public function state(string $country, string $rail): string
    {
        $country = strtoupper($country);
        if (in_array($country, PayoutSettings::deniedCountries(), true)) {
            return 'blocked';
        }
        $row = $this->rows()->get($country.'|'.$rail);
        if ($row === null || $row->admin_override === 'force_off') {
            return 'not_supported';
        }
        if ($row->admin_override === 'force_on') {
            return 'available';
        }
        if (! $row->provider_supports) {
            return 'not_supported';
        }

        return $this->corridorEnabled($country, $rail) ? 'available' : 'coming_soon';
    }

    public function isEnabled(string $country, string $rail): bool
    {
        return $this->state($country, $rail) === 'available';
    }

    /** @return array<string, string> rail => state for one country */
    public function railsFor(string $country, array $rails = self::MATRIX_RAILS): array
    {
        $out = [];
        foreach ($rails as $rail) {
            $out[$rail] = $this->state($country, $rail);
        }

        return $out;
    }

    /**
     * The full matrix: every ISO country x the four matrix rails, plus metadata.
     *
     * @return list<array{country: string, rails: array<string, string>, verified_at: ?string}>
     */
    public function matrix(): array
    {
        return Cache::remember($this->key('matrix'), 3600, function () {
            $verified = $this->rows()->groupBy(fn ($r) => $r->country)->map(fn ($g) => $g->max('verified_at'));
            $rows = [];
            foreach (self::countryCodes() as $c) {
                $rows[] = ['country' => $c, 'rails' => $this->railsFor($c), 'verified_at' => isset($verified[$c]) ? \Illuminate\Support\Carbon::parse($verified[$c])->toDateString() : null];
            }

            return $rows;
        });
    }

    /**
     * Every real ISO-3166 alpha-2 country (from ICU, so it needs no hand-kept list), minus
     * the pseudo-regions ICU also knows (EU, UN, exceptional reservations…).
     *
     * @return list<string>
     */
    public static function countryCodes(): array
    {
        return Cache::rememberForever('payout:rails:iso-codes', function () {
            $skip = ['EU', 'UN', 'EZ', 'XA', 'XB', 'QO', 'ZZ', 'AC', 'CP', 'DG', 'EA', 'IC', 'TA', 'CQ'];
            $codes = [];
            foreach (range('A', 'Z') as $a) {
                foreach (range('A', 'Z') as $b) {
                    $cc = $a.$b;
                    if (! in_array($cc, $skip, true) && \Locale::getDisplayRegion('-'.$cc, 'en') !== $cc) {
                        $codes[] = $cc;
                    }
                }
            }

            return $codes;
        });
    }

    /** Localised country name (ICU), falling back to the code. */
    public static function countryName(string $code, ?string $locale = null): string
    {
        $name = \Locale::getDisplayRegion('-'.strtoupper($code), $locale ?: app()->getLocale());

        return $name !== '' && $name !== strtoupper($code) ? $name : strtoupper($code);
    }

    /** Most recent verification across the whole registry (the guide footer). */
    public function lastVerifiedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->rows()->max('verified_at');
    }

    public function note(string $country, string $rail): ?PayoutCountryRail
    {
        return $this->rows()->get(strtoupper($country).'|'.$rail);
    }

    /** Bust every cached view of the registry (admin edit, audit, re-seed, corridor change). */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    private function key(string $name): string
    {
        return "payout:rails:{$name}:v".(int) Cache::get(self::VERSION_KEY, 0);
    }

    /** @return \Illuminate\Support\Collection<string, PayoutCountryRail> keyed "CC|rail" */
    private function rows()
    {
        return Cache::remember($this->key('rows'), 3600, fn () => Schema::hasTable('payout_country_rails')
            ? PayoutCountryRail::all()->keyBy(fn ($r) => $r->country.'|'.$r->rail)
            : collect());
    }

    private function corridorEnabled(string $country, string $rail): bool
    {
        // One query for every enabled corridor (cached), not one per cell: the matrix reads ~1,000 cells.
        $set = Cache::remember($this->key('corridors'), 600, fn () => PayoutCorridor::query()->enabled()->get(['country', 'provider'])
            ->mapWithKeys(fn ($c) => [$c->country.'|'.$c->provider => true])->all());

        foreach (self::providersFor($rail) as $provider) {
            if (isset($set[$country.'|'.$provider])) {
                return true;
            }
        }

        return false;
    }
}
