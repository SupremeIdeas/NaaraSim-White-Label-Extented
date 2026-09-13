<?php

namespace App\Livewire;

use App\Models\EsimPlan;
use App\Support\CountryNames;
use App\Support\EsimCatalogue;
use App\Support\EsimRegions;
use App\Support\PendingCoupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * eSIM catalogue (blueprint Sections 4, 12, 14 + BUILD-8 §3). Lists active plans
 * with the display price (USD + local via the accessor) — never cost.
 *
 * BUILD-8 nests a Popular / Local / Regional / Global browser INSIDE the existing
 * data/full tabs (§3.0 — the two lines are never mixed). Everything the browser
 * reads comes from the already-synced esim_plans table + the admin image tables
 * via EsimCatalogue — no live provider call is ever made while browsing (§3.5.3).
 * The premium hero slider above is left exactly as-is.
 */
#[Layout('components.layouts.customer')]
class Catalogue extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** Storefront line: 'data' (data-only) or 'full' (calls + data). Deep-linkable. */
    #[Url(as: 'tab')]
    public string $tab = 'data';

    /** Navigation segment within the line: popular | local | regional | global. */
    #[Url(as: 'view')]
    public string $view = 'popular';

    /** Selected local country (ISO2) — the country drill-down. Deep-linkable. */
    #[Url(as: 'country')]
    public string $country = '';

    /** Selected region slug (or 'world' for global) — the region drill-down. */
    #[Url(as: 'region')]
    public string $region = '';

    /** Selected plan id — opens the dedicated plan-detail view (§3.4). */
    #[Url(as: 'plan')]
    public ?int $planId = null;

    /** A coupon claimed from an announcement (?claim=CODE) — stashed for checkout. */
    #[Url(as: 'claim')]
    public string $claim = '';

    private const VIEWS = ['popular', 'local', 'regional', 'global'];

    /**
     * Batch 8: the full voice eSIM line (calls + SMS) is a tier-locked feature.
     * When a white-label fork's license locks it, only data-only plans are sold
     * — the Full tab is hidden and any attempt to select it falls back to data.
     * Inert on the master (never locked there).
     */
    public function getVoiceLockedProperty(): bool
    {
        return \App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_ESIM_VOICE);
    }

    public function setTab(string $tab): void
    {
        $allowed = $this->voiceLocked ? ['data'] : ['data', 'full'];
        $this->tab = in_array($tab, $allowed, true) ? $tab : 'data';
        // A country/region with data plans may have none on the Full line (and
        // vice versa), so every drill-down resets when switching lines.
        $this->resetSelection();
        $this->view = 'popular';
        $this->resetPage();
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'popular';
        $this->resetSelection();
        $this->resetPage();
    }

    /** Drill into a single local country's plan list. */
    public function openCountry(string $code): void
    {
        $this->resetSelection();
        $this->view = 'local';
        $this->country = strtoupper(trim($code));
        $this->resetPage();
    }

    /** Drill into a region's (or 'other'/'world') plan list. */
    public function openRegion(string $slug): void
    {
        $this->resetSelection();
        $slug = trim($slug);
        if ($slug === EsimRegions::WORLD) {
            $this->view = 'global';
        } else {
            $this->view = 'regional';
        }
        $this->region = $slug;
        $this->resetPage();
    }

    /** Open the single worldwide tile (Global tab). */
    public function openGlobal(): void
    {
        $this->openRegion(EsimRegions::WORLD);
    }

    /** Open the dedicated plan-detail view for a plan card. */
    public function openPlan(int $id): void
    {
        $this->planId = $id;
    }

    /** Contextual back: detail → list → grid. */
    public function back(): void
    {
        if ($this->planId !== null) {
            $this->planId = null;

            return;
        }
        $this->resetSelection();
        $this->resetPage();
    }

    public function clearCountry(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    private function resetSelection(): void
    {
        $this->country = '';
        $this->region = '';
        $this->planId = null;
    }

    /** Open the shared country picker, scoped to the active line. */
    public function browseCountries(): void
    {
        $this->dispatch('open-country-picker',
            source: 'esim',
            args: ['has_voice' => $this->tab === 'full'],
            for: 'catalogue',
            title: 'Browse eSIMs by country');
    }

    #[On('country-picked')]
    public function onCountryPicked(string $code, string $name, string $for): void
    {
        if ($for !== 'catalogue') {
            return; // another opener's pick — ignore
        }
        $this->openCountry($code);
    }

    public function mount(): void
    {
        if ($this->claim !== '') {
            PendingCoupon::stash($this->claim);
        }
        // Never land a locked fork on the Full (voice) line via a ?tab=full deep link.
        if ($this->voiceLocked) {
            $this->tab = 'data';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** active + current-line scope — every plan query starts here. */
    private function lineQuery(bool $hasVoice): Builder
    {
        return EsimPlan::query()->where('is_active', true)->where('has_voice', $hasVoice);
    }

    public function render()
    {
        $tab = in_array($this->tab, ['data', 'full'], true) ? $this->tab : 'data';
        $hasVoice = $tab === 'full';
        $view = in_array($this->view, self::VIEWS, true) ? $this->view : 'popular';
        $country = strtoupper(trim($this->country));
        $region = trim($this->region);
        $search = trim($this->search);

        $data = [
            'tab' => $tab,
            'view' => $view,
            'search' => $search,
            'fullCount' => $this->lineQuery(true)->count(),
        ];

        // 1) Plan-detail view — a real screen, not an inline expand (§3.4).
        if ($this->planId !== null) {
            $plan = $this->lineQuery($hasVoice)->find($this->planId);
            if ($plan) {
                return view('livewire.catalogue', array_merge($data, [
                    'screen' => 'detail',
                    'plan' => $plan,
                    'banner' => $this->bannerFor($plan),
                    'facts' => $this->facts($plan),
                ]));
            }
            $this->planId = null; // stale link — fall through to the grid
        }

        $grid = EsimCatalogue::grid($hasVoice);

        // 2) Live local search across plan names + country/region tile labels (§3.1).
        if ($search !== '') {
            $needle = mb_strtolower($search);

            return view('livewire.catalogue', array_merge($data, [
                'screen' => 'search',
                'plans' => $this->lineQuery($hasVoice)
                    ->where('name', 'like', "%{$search}%")
                    ->orderByDesc('is_featured')->orderBy('name')->paginate(12),
                'countryHits' => array_values(array_filter($grid['local'],
                    fn ($r) => str_contains(mb_strtolower($r['name']), $needle))),
                'regionHits' => array_values(array_filter($grid['regions'],
                    fn ($r) => str_contains(mb_strtolower($r['label']), $needle))),
            ]));
        }

        // 3) A selected local country — banner + its plan list (§3.3).
        if ($country !== '') {
            return view('livewire.catalogue', array_merge($data, [
                'screen' => 'country',
                'selCode' => $country,
                'selName' => CountryNames::name($country),
                'banner' => EsimCatalogue::countryBanner($country),
                'plans' => $this->lineQuery($hasVoice)
                    ->whereJsonContains('countries', $country)
                    ->where(fn ($q) => $q->where('coverage_type', EsimPlan::COVERAGE_LOCAL)->orWhereNull('coverage_type'))
                    ->orderByDesc('is_featured')->orderBy('final_retail_usd')->paginate(12),
            ]));
        }

        // 4) A selected region / global — banner + its plan list (§3.3).
        if ($region !== '') {
            $isGlobal = $region === EsimRegions::WORLD;

            return view('livewire.catalogue', array_merge($data, [
                'screen' => 'region',
                'selSlug' => $region,
                'selName' => $isGlobal ? 'Global' : ($region === EsimCatalogue::REGION_OTHER ? 'Other regions' : EsimRegions::label($region)),
                'banner' => EsimCatalogue::regionBanner($isGlobal ? EsimRegions::WORLD : $region),
                'plans' => $this->lineQuery($hasVoice)
                    ->when($isGlobal,
                        fn ($q) => $q->where('coverage_type', EsimPlan::COVERAGE_GLOBAL),
                        fn ($q) => $q->where('coverage_type', EsimPlan::COVERAGE_REGIONAL)
                            ->when($region === EsimCatalogue::REGION_OTHER,
                                fn ($q2) => $q2->whereNull('region_slug'),
                                fn ($q2) => $q2->where('region_slug', $region)))
                    ->orderByDesc('is_featured')->orderBy('final_retail_usd')->paginate(12),
            ]));
        }

        // 5) The default grid for the active segment (Popular/Local/Regional/Global).
        $plans = null;
        $globalBanner = null;
        $popularDestinations = [];
        if ($view === 'popular') {
            $featured = $this->lineQuery($hasVoice)->where('is_featured', true);
            // Until an admin curates the Popular tab, fall back to every plan on
            // the line so the default landing view is never empty.
            $base = (clone $featured)->doesntExist() ? $this->lineQuery($hasVoice) : $featured;
            $plans = $base->orderByDesc('is_featured')->orderBy('name')->paginate(12);
            // Photo-card row of the same "featured" countries (theme-shared —
            // owner request: one layout, every theme, not a per-theme variant).
            $popularDestinations = EsimCatalogue::popularDestinations($hasVoice);
        } elseif ($view === 'global') {
            // Global goes straight to its banner + plan list (reference layout) —
            // there is only ever the one worldwide grouping, so no tile step.
            $plans = $this->lineQuery($hasVoice)->where('coverage_type', EsimPlan::COVERAGE_GLOBAL)
                ->orderByDesc('is_featured')->orderBy('final_retail_usd')->paginate(12);
            $globalBanner = EsimCatalogue::regionBanner(EsimRegions::WORLD);
        }

        return view('livewire.catalogue', array_merge($data, [
            'screen' => 'grid',
            'grid' => $grid,
            'plans' => $plans,
            'globalBanner' => $globalBanner,
            'globalCount' => $grid['global']['count'] ?? 0,
            'popularDestinations' => $popularDestinations,
        ]));
    }

    /** The banner image for a plan's coverage (country/region/global) or null. */
    private function bannerFor(EsimPlan $plan): ?string
    {
        if ($plan->coverage_type === EsimPlan::COVERAGE_GLOBAL) {
            return EsimCatalogue::regionBanner(EsimRegions::WORLD);
        }
        if ($plan->coverage_type === EsimPlan::COVERAGE_REGIONAL && $plan->region_slug) {
            return EsimCatalogue::regionBanner($plan->region_slug);
        }
        $first = collect((array) $plan->countries)->first(fn ($c) => strlen((string) $c) === 2);

        return $first ? EsimCatalogue::countryBanner(strtoupper((string) $first)) : null;
    }

    /**
     * Honest, provider-agnostic facts for the plan-detail view (§3.4). Only facts
     * we can prove from data we already hold — data amount, validity, coverage,
     * and voice — are shown. Assumed feature badges (5G/hotspot/top-up) are NOT
     * fabricated: none of the current providers' confirmed responses expose those
     * fields, so they are intentionally omitted rather than guessed.
     *
     * @return list<array{icon: string, label: string}>
     */
    private function facts(EsimPlan $plan): array
    {
        $facts = [];
        $facts[] = ['icon' => 'signal', 'label' => $plan->data_mb
            ? rtrim(rtrim(number_format($plan->data_mb / 1024, 1), '0'), '.').' GB data'
            : 'Unlimited data'];
        if ($plan->validity_days) {
            $facts[] = ['icon' => 'refresh', 'label' => $plan->validity_days.' days validity'];
        }
        $facts[] = ['icon' => 'phone', 'label' => $plan->has_voice ? 'Calls + SMS included' : 'Data-only eSIM'];

        $coverage = match ($plan->coverage_type) {
            EsimPlan::COVERAGE_GLOBAL => 'Worldwide coverage',
            EsimPlan::COVERAGE_REGIONAL => ($plan->region_slug ? EsimRegions::label($plan->region_slug).' region' : 'Multi-country coverage'),
            default => count((array) $plan->countries).' '.Str::plural('country', count((array) $plan->countries)),
        };
        $facts[] = ['icon' => 'globe', 'label' => $coverage];

        return $facts;
    }
}
