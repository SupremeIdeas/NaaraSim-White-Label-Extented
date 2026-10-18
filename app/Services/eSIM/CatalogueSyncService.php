<?php

namespace App\Services\eSIM;

use App\Models\EsimPlan;
use App\Services\Pricing\PricingEngine;
use App\Support\CountryPickerSources;
use App\Support\EsimRegions;
use App\Support\ProviderModels;
use App\Support\SupplierScrub;
use App\Support\SyncStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Syncs each provider's catalogue into esim_plans and recomputes retail
 * through the PricingEngine (blueprint Sections 5.2-5.4 & 5.3.3).
 *
 * The money-critical rules, per provider:
 *   - eSIM Go / Quibity: the catalogue `price` is the WHOLESALE cost.
 *   - Airalo: `net_price` is the cost; `minimum_selling_price` is the
 *     contractual floor (stored in airalo_min_price). NEVER store Airalo's
 *     own `price` as our retail.
 * cost_price_usd is always PRIVATE.
 */
class CatalogueSyncService
{
    /** provider key => display label (single source for the CLI + admin UI). */
    public const PROVIDERS = [
        'esimgo' => 'eSIM Go',
        'airalo' => 'Airalo',
        'quibity' => 'Quibity',
        'zendit' => 'Zendit',
        'oneglobal' => '1GLOBAL',
        'montymobile' => 'Monty Mobile',
        'gigs' => 'Gigs',
    ];

    public function __construct(private readonly PricingEngine $pricing) {}

    /** Sync one provider; returns the number of plans upserted. Records the
     *  outcome (success/fail + count/error) for admin observability either way. */
    public function sync(string $provider): int
    {
        // Tier 4 #10 Phase A1: a provider with no API key configured yet
        // (real credentials not entered — not onboarded, not a failure) was
        // attempted every single cycle regardless, guaranteeing a permanent,
        // zero-value error-log entry. Skip it at info level instead: "not
        // configured" is a legitimate, visible, NEUTRAL state (surfaced
        // distinctly by SyncStatus below), never silently hidden and never
        // indistinguishable from a genuine provider failure.
        if (! ProviderModels::providerConfigured($provider)) {
            SyncStatus::record($provider, ok: true, count: 0, skipped: true);
            Log::info("[esim] Catalogue sync skipped for {$provider}: not configured.");

            return 0;
        }

        try {
            $count = $this->doSync($provider);
            SyncStatus::record($provider, ok: true, count: $count);
            \App\Support\NaaraConnect::flush();   // a synced voice plan can switch the Data + Calls line on

            return $count;
        } catch (\Throwable $e) {
            SyncStatus::record($provider, ok: false, error: $e->getMessage());
            Log::warning("[esim] Catalogue sync failed for {$provider}: ".$e->getMessage());
            throw $e;
        }
    }

    private function doSync(string $provider): int
    {
        $rows = match ($provider) {
            'esimgo' => $this->mapEsimGo(app('esim.esimgo')->getCatalogue()),
            'airalo' => $this->mapAiralo(app('esim.airalo')->getCatalogue()),
            'quibity' => $this->mapQuibity(app('esim.quibity')->getCatalogue()),
            'zendit' => $this->mapZendit(app('esim.zendit')->getCatalogue()),
            'gigs' => $this->mapGigs(app('esim.gigs')->getCatalogue()),
            'oneglobal' => $this->mapOneGlobal(app('esim.oneglobal')->getCatalogue()),
            'montymobile' => $this->mapFullEsim($provider, app("esim.{$provider}")->getCatalogue()),
            default => throw new \InvalidArgumentException("Unknown eSIM provider [$provider]."),
        };

        // Plans whose tooltip-relevant fields changed this sync — only these get
        // a (queued) tooltip regeneration afterwards, to control cost (§5.1).
        $tooltipDirty = [];

        foreach ($rows as $row) {
            $plan = EsimPlan::updateOrCreate(
                ['provider' => $provider, 'provider_plan_id' => $row['provider_plan_id']],
                [
                    // Scrub any supplier brand out of the name before storing it,
                    // so provider identity can never leak to users (rule 1.2).
                    'name' => SupplierScrub::name((string) $row['name']),
                    'type' => $row['type'] ?? null,
                    // Region/coverage categorisation (BUILD-8 §2.1). Derived from
                    // each provider's real signal at map time; both may be null on
                    // a provider that supplies neither (expected, not a gap).
                    'coverage_type' => $row['coverage_type'] ?? null,
                    'region_slug' => $row['region_slug'] ?? null,
                    'has_voice' => $row['has_voice'] ?? false, // Naara Connect (Zendit) only
                    'data_mb' => $row['data_mb'] ?? null,
                    'validity_days' => $row['validity_days'] ?? null,
                    'countries' => $row['countries'] ?? [],
                    'cost_price_usd' => $row['cost_price_usd'],           // PRIVATE
                    'airalo_min_price' => $row['airalo_min_price'] ?? null, // Airalo only
                    'is_active' => true,
                    'synced_at' => now(),
                ],
            );

            // Capture "content changed" BEFORE recompute() saves the plan again
            // (recompute only touches price columns, which don't affect a tooltip).
            if ($plan->wasRecentlyCreated
                || $plan->wasChanged(['name', 'data_mb', 'validity_days', 'countries', 'coverage_type', 'region_slug', 'has_voice'])) {
                $tooltipDirty[] = $plan->id;
            }

            // Recompute retail through the single pricing owner (Part 13).
            $this->pricing->recompute($plan);
        }

        // The country picker's per-country tallies and the §3 navigation grid
        // both derive from esim_plans, which just changed.
        CountryPickerSources::flush();
        \App\Support\EsimCatalogue::flush();

        // Queue AI tooltips for the changed plans only (§5) — never synchronous,
        // and a no-op when no Anthropic key is configured.
        if ($tooltipDirty !== [] && app(\App\Services\eSIM\EsimTooltipService::class)->enabled()) {
            \App\Jobs\GenerateEsimTooltipsJob::dispatch($tooltipDirty);
        }

        return count($rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function mapEsimGo(array $raw): array
    {
        $bundles = $raw['bundles'] ?? $raw;

        return collect($bundles)->map(function ($b) {
            $countries = $this->isoList($b['countries'] ?? []);

            // eSIM Go exposes a real, distinct `region` filter (e.g. "Europe") —
            // confirmed in §1 — so that's the region signal here.
            return array_merge([
                'provider_plan_id' => $b['name'] ?? $b['bundle_name'] ?? null,
                'name' => $b['description'] ?? $b['name'] ?? 'eSIM bundle',
                'type' => $b['type'] ?? null,
                // eSIM Go reports an unlimited bundle as dataAmount -1 with `unlimited: true`; across the platform unlimited is data_mb = null (the fair-usage
                // disclosure, failover matching and display all key on it), so -1 must never be stored.
                'data_mb' => ! empty($b['unlimited']) || (is_numeric($b['dataAmount'] ?? null) && (int) $b['dataAmount'] < 0) ? null : $this->intOrNull($b['dataAmount'] ?? $b['data'] ?? null),
                'validity_days' => $this->intOrNull($b['duration'] ?? null),
                'countries' => $countries,
                'cost_price_usd' => (float) ($b['price'] ?? 0),
            ], $this->deriveCoverage($countries, $b['region'] ?? null));
        })->filter(fn ($r) => $r['provider_plan_id'] !== null)->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function mapAiralo(array $data): array
    {
        $rows = [];

        foreach ($data as $group) {
            foreach (Arr::get($group, 'operators', []) as $operator) {
                $countries = collect(Arr::get($operator, 'countries', []))
                    ->pluck('country_code')->filter()
                    ->map(fn ($c) => strtoupper((string) $c))->values()->all();

                // Airalo (confirmed §1): the operator `type` distinguishes local
                // from global; regional/global operators carry a `slug` such as
                // "europe" or "world". Those are the real region signals — the
                // group title is a last-resort human label for the same grouping.
                $regionRaw = Arr::get($operator, 'slug')
                    ?? Arr::get($group, 'slug')
                    ?? Arr::get($group, 'title');
                $coverage = $this->deriveCoverage($countries, $regionRaw, Arr::get($operator, 'type'));

                foreach (Arr::get($operator, 'packages', []) as $pkg) {
                    if (! isset($pkg['id'])) {
                        continue;
                    }
                    $rows[] = array_merge([
                        'provider_plan_id' => (string) $pkg['id'],
                        'name' => $pkg['title'] ?? $pkg['id'],
                        'type' => $pkg['type'] ?? 'sim',
                        'data_mb' => $this->intOrNull($pkg['amount'] ?? null),
                        'validity_days' => $this->intOrNull($pkg['day'] ?? null),
                        'countries' => $countries,
                        // net_price is our cost; minimum_selling_price is the floor.
                        'cost_price_usd' => (float) ($pkg['net_price'] ?? 0),
                        'airalo_min_price' => isset($pkg['minimum_selling_price'])
                            ? (float) $pkg['minimum_selling_price']
                            : null,
                    ], $coverage);
                }
            }
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function mapQuibity(array $raw): array
    {
        $plans = $raw['plans'] ?? $raw['data'] ?? $raw;

        return collect($plans)->reject(fn ($p) => ! empty($p['hasPhoneNumber']) || (isset($p['isActive']) && ! $p['isActive']))->map(function ($p) {
            $countries = $this->isoList($p['countries'] ?? []);
            $unlimited = ! empty($p['isUnlimited']);

            // Fields per the partner API (docs.esim.sm): `mb`, `days`, `price` (list) and `salePrice` (the discounted price). The platform's MarginGuard must never sell
            // below what a plan really costs and the order answer is the only place the true charge shows, so the HIGHER of the two is the working cost until it is confirmed.
            // Plans that bundle a phone number are a different product (Naara Connect lane) and are not ingested as data plans.
            return array_merge([
                'provider_plan_id' => isset($p['id']) ? (string) $p['id'] : null,
                'name' => $p['name'] ?? 'eSIM plan',
                'type' => $p['type'] ?? null,
                'data_mb' => $unlimited ? null : $this->intOrNull($p['mb'] ?? $p['data_mb'] ?? null),
                'validity_days' => $this->intOrNull($p['days'] ?? $p['validity_days'] ?? null),
                'countries' => $countries,
                'cost_price_usd' => (float) max((float) ($p['price'] ?? 0), (float) ($p['salePrice'] ?? 0)),
            ], $this->deriveCoverage($countries, $p['region'] ?? null));
        })->filter(fn ($r) => $r['provider_plan_id'] !== null)->values()->all();
    }

    /**
     * Zendit serves BOTH lines. Every offer is ingested; `has_voice` (set from
     * voiceMinutes / voiceUnlimited) routes it: a voice-capable offer is a Naara
     * Connect Full eSIM (calls + data), a data-only offer expands Naara Data. So
     * Zendit is a data-lane backup AND a Full-eSIM provider. Cost is
     * `cost.fixed / currencyDivisor` — the WHOLESALE price (PRIVATE); Zendit's
     * suggested `price` block is ignored (retail stays ours via PricingEngine).
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapZendit(array $raw): array
    {
        $offers = $raw['list'] ?? $raw['data'] ?? $raw;

        return collect($offers)
            ->filter(fn ($o) => ($o['enabled'] ?? true) && isset($o['offerId']))
            ->map(function ($o) {
                $cost = $o['cost'] ?? [];
                $divisor = (int) ($cost['currencyDivisor'] ?? 1) ?: 1;

                $unlimitedData = (bool) ($o['dataUnlimited'] ?? false);
                $dataGb = (float) ($o['dataGB'] ?? 0);
                $hasVoice = (bool) ($o['voiceUnlimited'] ?? false) || (int) ($o['voiceMinutes'] ?? 0) > 0;

                $countries = $this->isoList(array_merge(
                    array_filter([$o['country'] ?? null]),
                    $o['regions'] ?? [],
                ));

                // Zendit is categorised by country AND region (§1). Its `region`
                // (or the first entry of a `regions` array) is the region signal.
                $regionRaw = $o['region'] ?? (is_array($o['regions'] ?? null) ? ($o['regions'][0] ?? null) : ($o['regions'] ?? null));

                return array_merge([
                    'provider_plan_id' => (string) $o['offerId'],
                    'name' => $this->zenditName($o),
                    'type' => $hasVoice ? 'Voice + Data' : 'Data',
                    'has_voice' => $hasVoice,
                    // dataGB is in GB; store MB. Unlimited => null (matches the model).
                    'data_mb' => $unlimitedData ? null : ($dataGb > 0 ? (int) round($dataGb * 1024) : null),
                    'validity_days' => $this->intOrNull($o['durationDays'] ?? null),
                    'countries' => $countries,
                    'cost_price_usd' => (float) ($cost['fixed'] ?? 0) / $divisor,
                ], $this->deriveCoverage($countries, is_string($regionRaw) ? $regionRaw : null));
            })
            ->filter(fn ($r) => $r['provider_plan_id'] !== '')
            ->values()
            ->all();
    }

    /**
     * 1GLOBAL / Monty Mobile / Gigs → the Naara Connect (Full eSIM) line. These
     * three are voice+data MVNO providers, so every ingested plan is
     * has_voice = true. Their exact catalogue schemas are confirmed on partner
     * access; this reads the common REST field names defensively (list/data/
     * plans wrapper, id/planId, cost/wholesale/net price, data in GB or MB,
     * duration in days, ISO countries). Cost is WHOLESALE and PRIVATE.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapFullEsim(string $provider, array $raw): array
    {
        $plans = $raw['list'] ?? $raw['data'] ?? $raw['plans'] ?? $raw['items'] ?? $raw;

        return collect($plans)
            ->filter(fn ($p) => is_array($p) && ($p['enabled'] ?? true))
            ->map(function ($p) use ($provider) {
                $id = $p['id'] ?? $p['planId'] ?? $p['plan_id'] ?? $p['code'] ?? null;
                if ($id === null) {
                    return null;
                }

                // Cost (WHOLESALE, PRIVATE): accept a scalar or a {amount,divisor}
                // shape. Prefer explicit wholesale/net/cost keys over any retail.
                $cost = $p['cost'] ?? $p['wholesale'] ?? $p['wholesale_price'] ?? $p['net_price'] ?? $p['price'] ?? 0;
                if (is_array($cost)) {
                    $divisor = (int) ($cost['currencyDivisor'] ?? $cost['divisor'] ?? 1) ?: 1;
                    $cost = (float) ($cost['fixed'] ?? $cost['amount'] ?? 0) / $divisor;
                }

                // Data: GB (float) or MB (int); unlimited => null.
                $dataMb = null;
                if (! ($p['dataUnlimited'] ?? $p['data_unlimited'] ?? false)) {
                    if (isset($p['dataGB']) || isset($p['data_gb'])) {
                        $dataMb = (int) round(((float) ($p['dataGB'] ?? $p['data_gb'])) * 1024);
                    } elseif (isset($p['dataMB']) || isset($p['data_mb'])) {
                        $dataMb = $this->intOrNull($p['dataMB'] ?? $p['data_mb']);
                    }
                }

                $countries = $this->isoList($p['countries'] ?? $p['regions'] ?? array_filter([$p['country'] ?? null]));

                // 1GLOBAL has named regional + global tiers (§1); Monty Mobile
                // answers country-or-region queries; Gigs may expose no region
                // taxonomy at all — in which case region_slug simply stays null and
                // coverage falls back to the country count (expected, not a gap).
                $regionRaw = $p['region'] ?? (is_array($p['regions'] ?? null) ? ($p['regions'][0] ?? null) : null);

                return array_merge([
                    'provider_plan_id' => (string) $id,
                    'name' => $p['name'] ?? $p['title'] ?? $p['description'] ?? ucfirst($provider).' Full eSIM',
                    'type' => 'Voice + Data',
                    'has_voice' => true, // MVNO providers — always a Full eSIM
                    'data_mb' => $dataMb,
                    'validity_days' => $this->intOrNull($p['durationDays'] ?? $p['validity_days'] ?? $p['days'] ?? null),
                    'countries' => $countries,
                    'cost_price_usd' => (float) $cost,
                ], $this->deriveCoverage($countries, is_string($regionRaw) ? $regionRaw : null));
            })
            ->filter(fn ($r) => $r !== null && $r['provider_plan_id'] !== '')
            ->values()
            ->all();
    }

    /**
     * Gigs plans (GET /projects/{project}/plans?simType=eSIM): `allowances` {dataBytes, voiceSeconds, smsMessages} (null or -1 = unlimited,
     * 0 = none), `price` {amount in minor units, currency}, `coverage` {countries[]}. Only USD plans that need no address/device from the
     * buyer are sold (an auto-ordered eSIM cannot collect them), and only plans that really include voice go to Naara Connect; a data-only
     * Gigs plan is stored as a data plan, never mislabelled as calls + data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapGigs(array $raw): array
    {
        return collect($raw['items'] ?? [])
            ->filter(fn ($p) => is_array($p) && ($p['status'] ?? 'available') === 'available' && in_array('eSIM', (array) ($p['simTypes'] ?? ['eSIM']), true))
            ->filter(fn ($p) => strtoupper((string) data_get($p, 'price.currency', 'USD')) === 'USD')
            ->filter(fn ($p) => in_array(data_get($p, 'requirements.address', 'none'), ['none', null], true) && ! array_filter((array) data_get($p, 'requirements', []), fn ($v, $k) => $k !== 'address' && ! in_array($v, ['none', null, false], true), ARRAY_FILTER_USE_BOTH))
            ->map(function ($p) {
                $a = (array) ($p['allowances'] ?? []);
                $bytes = array_key_exists('dataBytes', $a) ? $a['dataBytes'] : ($p['data'] ?? 0);
                $voice = array_key_exists('voiceSeconds', $a) ? $a['voiceSeconds'] : ($p['voice'] ?? 0);
                $unlimited = static fn ($v) => $v === null || (is_numeric($v) && (int) $v === -1);
                $countries = $this->isoList((array) data_get($p, 'coverage.countries', []));

                return array_merge([
                    'provider_plan_id' => (string) ($p['id'] ?? ''),
                    'name' => $p['name'] ?? 'eSIM',
                    'type' => ($unlimited($voice) || (int) $voice > 0) ? 'Voice + Data' : 'Data',
                    'has_voice' => $unlimited($voice) || (int) $voice > 0,
                    'data_mb' => $unlimited($bytes) ? null : ((int) $bytes > 0 ? (int) round(((int) $bytes) / 1048576) : null),
                    'validity_days' => $this->gigsDays($p['validity'] ?? null),
                    'countries' => $countries,
                    'cost_price_usd' => (float) data_get($p, 'price.amount', 0) / 100,
                ], $this->deriveCoverage($countries, null));
            })
            ->filter(fn ($r) => $r['provider_plan_id'] !== '' && $r['cost_price_usd'] > 0)
            ->values()
            ->all();
    }

    /**
     * 1GLOBAL Connect products: {id, name, period + period_unit, data:{value, units}, footprint[]/activeCountries[], pricing, region}. The documented
     * product carries no voice field, so these are stored as DATA plans (never silently passed off as calls + data); a product that does declare
     * voice (a `voice` key with minutes) is treated as Naara Connect.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapOneGlobal(array $raw): array
    {
        return collect($raw['list'] ?? $raw['items'] ?? $raw['data'] ?? [])->filter(fn ($p) => is_array($p) && ! empty($p['id']))->map(function ($p) {
            $units = strtoupper((string) data_get($p, 'data.units', 'GB'));
            $val = data_get($p, 'data.value');
            $mb = is_numeric($val) && (float) $val > 0 ? (int) round((float) $val * match ($units) { 'TB' => 1048576, 'GB' => 1024, 'KB' => 1 / 1024, default => 1 }) : null;
            $days = is_numeric($p['period'] ?? null) ? (int) $p['period'] * match (strtoupper((string) ($p['period_unit'] ?? 'DAYS'))) { 'WEEKS' => 7, 'MONTHS' => 30, 'YEARS' => 365, default => 1 } : null;
            $countries = $this->isoList((array) ($p['activeCountries'] ?? $p['footprint'] ?? []));
            $price = data_get($p, 'pricing.price') ?? data_get($p, 'pricing.amount') ?? data_get($p, 'pricing.wholesale') ?? 0;
            $voice = (int) data_get($p, 'voice.value', data_get($p, 'voice', 0));

            return array_merge([
                'provider_plan_id' => (string) $p['id'], 'name' => $p['name'] ?? '1GLOBAL eSIM', 'type' => $voice > 0 ? 'Voice + Data' : 'Data', 'has_voice' => $voice > 0,
                'data_mb' => $mb, 'validity_days' => $days, 'countries' => $countries, 'cost_price_usd' => (float) (is_array($price) ? ($price['amount'] ?? 0) : $price),
            ], $this->deriveCoverage($countries, is_string($p['region'] ?? null) ? $p['region'] : null));
        })->filter(fn ($r) => $r['cost_price_usd'] > 0)->values()->all();
    }

    /** Gigs `validity` describes the subscription period; read a day count defensively (shape to confirm in the sandbox). */
    private function gigsDays(mixed $v): ?int
    {
        if (! is_array($v)) {
            return null;
        }
        foreach (['days', 'periodDays', 'lengthDays'] as $k) {
            if (isset($v[$k]) && is_numeric($v[$k])) {
                return (int) $v[$k];
            }
        }
        $len = $v['periodLength'] ?? $v['length'] ?? null;
        $unit = strtolower((string) ($v['periodUnit'] ?? $v['unit'] ?? 'day'));
        if (is_numeric($len)) {
            return (int) $len * match (true) { str_starts_with($unit, 'week') => 7, str_starts_with($unit, 'month') => 30, str_starts_with($unit, 'year') => 365, default => 1 };
        }

        return null;
    }

    /** A human name for a Zendit offer: "<brand> — <data> + <voice>". */
    private function zenditName(array $o): string
    {
        $brand = $o['brandName'] ?? $o['brand'] ?? 'eSIM';

        $data = ($o['dataUnlimited'] ?? false)
            ? 'Unlimited data'
            : (($gb = (float) ($o['dataGB'] ?? 0)) > 0 ? rtrim(rtrim(number_format($gb, 1), '0'), '.').'GB' : 'Data');

        $parts = [$data];
        if ($o['voiceUnlimited'] ?? false) {
            $parts[] = 'unlimited mins';
        } elseif (($min = (int) ($o['voiceMinutes'] ?? 0)) > 0) {
            $parts[] = $min.' mins';
        }
        if (($o['smsUnlimited'] ?? false)) {
            $parts[] = 'unlimited SMS';
        } elseif (($sms = (int) ($o['smsNumber'] ?? 0)) > 0) {
            $parts[] = $sms.' SMS';
        }

        return trim($brand).' — '.implode(' + ', $parts);
    }

    /**
     * A plan covering at least this many countries with NO explicit region name
     * is treated as global (the honest fallback for a genuine worldwide bundle a
     * provider didn't label). Below it, a multi-country plan is regional.
     */
    private const GLOBAL_COUNTRY_THRESHOLD = 100;

    /**
     * Derive coverage_type + region_slug from a provider's REAL signals only
     * (BUILD-8 §1/§2.1) — never invented:
     *   - $explicitType: a provider's own local/global marker (Airalo's operator
     *     `type`), when it has one.
     *   - $regionRaw: a provider's own region name/slug (eSIM Go `region`, Airalo
     *     `slug`, Zendit `region`, …), normalised to a canonical Naara slug.
     * With no region name at all, coverage_type falls back to the country count
     * (1 = local; ≥threshold = global; otherwise regional) and region_slug stays
     * null rather than guessing a grouping.
     *
     * @param  array<int, string>  $countries
     * @return array{coverage_type: string, region_slug: ?string}
     */
    private function deriveCoverage(array $countries, ?string $regionRaw = null, ?string $explicitType = null): array
    {
        $slug = EsimRegions::normalize($regionRaw);
        $count = count(array_filter($countries, fn ($c) => strlen((string) $c) === 2));

        // A provider's explicit "local" marker is authoritative.
        if ($explicitType !== null && strtolower($explicitType) === 'local') {
            return ['coverage_type' => EsimPlan::COVERAGE_LOCAL, 'region_slug' => null];
        }

        if ($slug === EsimRegions::WORLD) {
            return ['coverage_type' => EsimPlan::COVERAGE_GLOBAL, 'region_slug' => EsimRegions::WORLD];
        }
        if ($slug !== null) {
            return ['coverage_type' => EsimPlan::COVERAGE_REGIONAL, 'region_slug' => $slug];
        }

        // No real region name — fall back to the country count (§2.1).
        if ($count <= 1) {
            return ['coverage_type' => EsimPlan::COVERAGE_LOCAL, 'region_slug' => null];
        }
        if ($count >= self::GLOBAL_COUNTRY_THRESHOLD) {
            return ['coverage_type' => EsimPlan::COVERAGE_GLOBAL, 'region_slug' => null];
        }

        return ['coverage_type' => EsimPlan::COVERAGE_REGIONAL, 'region_slug' => null];
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Normalise a provider's countries field (list of ISO strings, or list of
     * objects with iso/code/name) to a flat list of codes.
     *
     * @return array<int, string>
     */
    private function isoList(mixed $countries): array
    {
        return collect($countries)->map(function ($c) {
            $value = is_string($c)
                ? $c
                : ($c['iso'] ?? $c['code'] ?? $c['country_code'] ?? $c['name'] ?? null);

            if (! is_string($value) || $value === '') {
                return null;
            }

            // Normalise 2-letter ISO codes to uppercase so storage is consistent
            // (the country picker + whereJsonContains filter match on 'NG', never
            // a mixed-case 'ng'). Non-ISO region names are left as-is.
            return (strlen($value) === 2 && ctype_alpha($value)) ? strtoupper($value) : $value;
        })->filter()->values()->all();
    }
}
