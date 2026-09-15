<?php

namespace App\Support;

use App\Models\ProviderOutcome;
use App\Models\ProviderRegistry;

/**
 * NAARA-BUILD-17 — read-only aggregations for the NCI Operations Center. Contains
 * ZERO business logic of its own (amendment / §0): it only shapes what Layers
 * 1–3 already wrote (provider_registry + provider_outcomes) into the groupings
 * and figures the admin pages render. Provider identities are never masked here —
 * admin manages real providers by their real names.
 */
class OperationsCenter
{
    /** Onboarding tiers, ascending — self-service first, enterprise last. */
    public const TIER_ORDER = ['self_service', 'individual_kyc', 'small_business', 'enterprise'];

    public static function tierLabel(?string $tier): string
    {
        return match ($tier) {
            'self_service' => 'Self-service',
            'individual_kyc' => 'Individual KYC',
            'small_business' => 'Business',
            'enterprise' => 'Enterprise',
            default => ucfirst((string) $tier),
        };
    }

    /** The public Naara product-family name for a family key (rebrands with white-label). */
    public static function familyName(string $familyKey): string
    {
        return ProviderModels::find($familyKey)['name'] ?? ucfirst(str_replace('_', ' ', $familyKey));
    }

    /**
     * The registry grouped by Naara product family — a provider serving more than
     * one family appears under each. Within a family, sorted by onboarding tier
     * (ascending) then name, so it reads as a tiered supply chain. Optional filters:
     * stack, status, circuit, tier.
     *
     * @param  array<string,string>  $filters
     * @return array<string, array{name:string, rows:list<array<string,mixed>>}>
     */
    public static function groupedByFamily(array $filters = []): array
    {
        $rows = ProviderRegistry::query()->orderBy('provider_key')->get()
            ->filter(fn (ProviderRegistry $r) => self::passesFilters($r, $filters));

        $groups = [];
        foreach ($rows as $r) {
            foreach (($r->product_families ?? []) as $family) {
                $groups[$family]['name'] ??= self::familyName($family);
                $groups[$family]['rows'][] = self::rowView($r);
            }
        }

        foreach ($groups as $family => &$group) {
            usort($group['rows'], function ($a, $b) {
                $ta = array_search($a['onboarding_tier'], self::TIER_ORDER, true);
                $tb = array_search($b['onboarding_tier'], self::TIER_ORDER, true);
                $ta = $ta === false ? 99 : $ta;
                $tb = $tb === false ? 99 : $tb;

                return [$ta, $a['provider_key']] <=> [$tb, $b['provider_key']];
            });
        }
        unset($group);

        // Present families in the same order the customer surfaces use.
        $order = ['naara_data', 'naara_connect', 'naara_verify', 'naara_rent', 'naara_line'];
        uksort($groups, function ($a, $b) use ($order) {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);

            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
        });

        return $groups;
    }

    /** @return array<string,mixed> */
    public static function rowView(ProviderRegistry $r): array
    {
        return [
            'provider_key' => $r->provider_key,
            'stack' => $r->stack,
            'onboarding_tier' => $r->onboarding_tier,
            'tier_label' => self::tierLabel($r->onboarding_tier),
            'status' => $r->status,
            'circuit' => $r->circuit_breaker_state,
            'latency_ms' => $r->latency_ms,
            'success_rate_24h' => $r->success_rate_24h,
            'nci_score' => $r->nci_score,
            'nci_confidence' => $r->nci_confidence,
            'nci_risk_rating' => $r->nci_risk_rating,
            'enabled' => (bool) $r->enabled,
            'paused_at' => $r->paused_at,
            'dashboard_login_url' => $r->dashboard_login_url,
            'balance' => $r->balance,
        ];
    }

    /**
     * A volume-based burn estimate from recent provider_outcomes (§5 Wallets) —
     * successful purchases per day over the last 7 days. Deliberately a volume
     * proxy, not a fabricated money figure: outcomes carry no cost, so this
     * reuses the outcome log rather than inventing a separate tracker.
     */
    public static function burnPerDay(string $providerKey): float
    {
        $succ = ProviderOutcome::where('provider_key', $providerKey)
            ->where('outcome', ProviderOutcome::SUCCESS)
            ->where('occurred_at', '>=', now()->subDays(7))->count();

        return round($succ / 7, 2);
    }

    /** @param array<string,string> $filters */
    private static function passesFilters(ProviderRegistry $r, array $filters): bool
    {
        foreach (['stack' => 'stack', 'status' => 'status', 'circuit' => 'circuit_breaker_state', 'tier' => 'onboarding_tier'] as $filterKey => $column) {
            $want = $filters[$filterKey] ?? '';
            if ($want !== '' && (string) $r->{$column} !== $want) {
                return false;
            }
        }

        return true;
    }
}
