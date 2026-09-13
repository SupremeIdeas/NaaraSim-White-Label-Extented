<?php

namespace App\Livewire\Admin;

use App\Jobs\GenerateEsimTooltipsJob;
use App\Jobs\SyncEsimCatalogueJob;
use App\Models\EsimCountryImage;
use App\Models\EsimPlan;
use App\Models\EsimRegionImage;
use App\Services\eSIM\CatalogueSyncService;
use App\Services\eSIM\EsimMarginAdvisor;
use App\Services\eSIM\EsimTooltipService;
use App\Services\Pricing\PricingEngine;
use App\Support\Auditor;
use App\Support\CountryNames;
use App\Support\EsimCatalogue;
use App\Support\EsimRegions;
use App\Support\MediaStorage;
use App\Support\SyncStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin → eSIM Control Center (BUILD-8 §4). One dedicated section for the whole
 * eSIM catalogue: per-provider sync status + trigger, Popular curation, per-plan
 * & bulk margin editing, Claude-assisted margin SUGGESTIONS (never auto-applied),
 * AI-tooltip management, and country/region image management.
 *
 * Gated by the `esim.manage` staff scope (super_admin/admin bypass via
 * Gate::before), so it can be delegated to staff without full admin (§4.7).
 * Cost is shown here because this whole surface is admin-only.
 */
#[Layout('components.layouts.admin')]
class EsimControlCenter extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $section = 'sync'; // sync | plans | images

    /** ---- Plans filters ---- */
    public string $fProvider = '';

    public string $fCoverage = '';

    public string $fRegion = '';

    public string $fCountry = '';

    public string $fSearch = '';

    /** ---- Per-plan margin edit buffer ---- */
    public ?int $editingMarginId = null;

    public string $marginValue = '';

    /** Claude suggestions keyed by plan id: ['markup'=>float,'rationale'=>string]. */
    public array $suggestions = [];

    /** ---- Per-plan tooltip override buffer ---- */
    public ?int $editingTooltipId = null;

    public string $tooltipValue = '';

    /** ---- Per-plan fair-usage note override buffer (Prompt 10) ---- */
    public ?int $editingFairUsageId = null;

    public string $fairUsageValue = '';

    /** ---- Bulk margin ---- */
    public string $bulkMargin = '';

    /** ---- Images ---- */
    public string $imageKind = 'country'; // country | region

    public string $imgCountry = '';

    public string $imgRegion = '';

    public $iconUpload = null;

    public $detailUpload = null;

    public ?string $flash = null;

    public function booted(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('esim.manage'),
            403
        );
    }

    public function setSection(string $section): void
    {
        $this->section = in_array($section, ['sync', 'plans', 'images'], true) ? $section : 'sync';
        $this->resetPage();
    }

    public function updated($name): void
    {
        if (in_array($name, ['fProvider', 'fCoverage', 'fRegion', 'fCountry', 'fSearch'], true)) {
            $this->resetPage();
        }
    }

    // ------------------------------------------------------------------ SYNC

    public function syncProvider(string $provider): void
    {
        if (! array_key_exists($provider, CatalogueSyncService::PROVIDERS)) {
            return;
        }
        SyncEsimCatalogueJob::dispatch($provider);
        Auditor::log('esim.sync_triggered', 'provider', null, ['provider' => $provider]);
        $this->flash = 'Queued a sync for '.CatalogueSyncService::PROVIDERS[$provider].'.';
    }

    public function syncAll(): void
    {
        foreach (array_keys(CatalogueSyncService::PROVIDERS) as $provider) {
            SyncEsimCatalogueJob::dispatch($provider);
        }
        Auditor::log('esim.sync_triggered', 'provider', null, ['provider' => 'all']);
        $this->flash = 'Queued a sync for all providers.';
    }

    // ----------------------------------------------------------------- PLANS

    public function togglePopular(int $id): void
    {
        $plan = EsimPlan::find($id);
        if (! $plan) {
            return;
        }
        $plan->is_featured = ! $plan->is_featured;
        $plan->save();
        EsimCatalogue::flush();
        Auditor::log('esim.popular_toggled', 'esim_plan', $id, ['is_featured' => $plan->is_featured]);
    }

    public function editMargin(int $id): void
    {
        $plan = EsimPlan::find($id);
        $this->editingMarginId = $id;
        $this->marginValue = $plan?->override_markup_pct !== null ? (string) $plan->override_markup_pct : '';
    }

    public function saveMargin(int $id, PricingEngine $engine): void
    {
        $plan = EsimPlan::find($id);
        if (! $plan) {
            return;
        }
        $this->validate(['marginValue' => 'nullable|numeric|min:0|max:1000']);

        $plan->override_markup_pct = $this->marginValue === '' ? null : (float) $this->marginValue;
        $plan->save();
        $engine->recompute($plan); // MarginGuard still floors the result
        EsimCatalogue::flush();

        Auditor::log('esim.margin_updated', 'esim_plan', $id, ['override_markup_pct' => $plan->override_markup_pct]);
        // A suggestion, if one was showing, is now resolved (accepted/edited).
        unset($this->suggestions[$id]);
        $this->editingMarginId = null;
        $this->marginValue = '';
        $this->flash = 'Margin updated.';
    }

    public function cancelMargin(): void
    {
        $this->editingMarginId = null;
        $this->marginValue = '';
    }

    public function suggestMargin(int $id, EsimMarginAdvisor $advisor): void
    {
        if (! $advisor->enabled()) {
            $this->flash = 'Add an Anthropic API key to use Claude suggestions.';

            return;
        }
        $plan = EsimPlan::find($id);
        if (! $plan) {
            return;
        }
        try {
            $s = $advisor->suggest($plan);
            $this->suggestions[$id] = ['markup' => $s['markup_pct'], 'rationale' => $s['rationale']];
            Auditor::log('esim.margin_suggested', 'esim_plan', $id, $this->suggestions[$id]);
        } catch (\Throwable $e) {
            $this->flash = 'Could not generate a suggestion right now.';
        }
    }

    /** Accept a suggestion into the edit buffer (admin still saves explicitly). */
    public function applySuggestion(int $id): void
    {
        if (! isset($this->suggestions[$id])) {
            return;
        }
        $this->editingMarginId = $id;
        $this->marginValue = (string) $this->suggestions[$id]['markup'];
    }

    public function dismissSuggestion(int $id): void
    {
        if (isset($this->suggestions[$id])) {
            Auditor::log('esim.margin_suggestion_rejected', 'esim_plan', $id, $this->suggestions[$id]);
            unset($this->suggestions[$id]);
        }
    }

    public function applyBulkMargin(PricingEngine $engine): void
    {
        $this->validate(['bulkMargin' => 'required|numeric|min:0|max:1000']);
        $value = (float) $this->bulkMargin;

        $count = 0;
        // Set the override then recompute each affected plan (MarginGuard floors
        // every result). Chunked so a large filtered set stays memory-safe;
        // internal math only, no external calls.
        $this->filteredQuery()->select('id')->chunkById(500, function ($chunk) use ($value, $engine, &$count) {
            EsimPlan::whereIn('id', $chunk->pluck('id'))->update(['override_markup_pct' => $value]);
            EsimPlan::whereIn('id', $chunk->pluck('id'))->get()
                ->each(fn (EsimPlan $p) => $engine->recompute($p));
            $count += $chunk->count();
        });

        if ($count === 0) {
            $this->flash = 'No plans match the current filter.';

            return;
        }

        EsimCatalogue::flush();
        Auditor::log('esim.margin_bulk_updated', 'esim_plan', null, ['count' => $count, 'markup' => $value, 'filter' => $this->activeFilterSummary()]);
        $this->bulkMargin = '';
        $this->flash = "Applied {$value}% margin to {$count} plan(s).";
    }

    // --------------------------------------------------------------- TOOLTIPS

    public function regenerateTooltip(int $id, EsimTooltipService $tooltips): void
    {
        if (! $tooltips->enabled()) {
            $this->flash = 'Add an Anthropic API key to generate tooltips.';

            return;
        }
        GenerateEsimTooltipsJob::dispatch([$id], force: true);
        Auditor::log('esim.tooltip_regenerated', 'esim_plan', $id);
        $this->flash = 'Queued tooltip regeneration.';
    }

    public function editTooltip(int $id): void
    {
        $plan = EsimPlan::find($id);
        $this->editingTooltipId = $id;
        $this->tooltipValue = (string) ($plan?->ai_tooltip_override ?? '');
    }

    public function saveTooltip(int $id): void
    {
        $plan = EsimPlan::find($id);
        if (! $plan) {
            return;
        }
        $this->validate(['tooltipValue' => 'nullable|string|max:400']);
        $plan->ai_tooltip_override = trim($this->tooltipValue) ?: null;
        $plan->save();
        EsimCatalogue::flush();
        Auditor::log('esim.tooltip_override_saved', 'esim_plan', $id, ['has_override' => filled($plan->ai_tooltip_override)]);
        $this->editingTooltipId = null;
        $this->tooltipValue = '';
        $this->flash = 'Tooltip saved.';
    }

    public function cancelTooltip(): void
    {
        $this->editingTooltipId = null;
        $this->tooltipValue = '';
    }

    // ------------------------------------------------------------ FAIR USAGE

    /**
     * Prompt 10: an unlimited plan's real, provider-published fair-usage
     * threshold (when known) — the honest generic disclosure covers it
     * otherwise, so this is only ever an admin OPTION, never required to ship.
     */
    public function editFairUsage(int $id): void
    {
        $plan = EsimPlan::find($id);
        if (! $plan || $plan->data_mb !== null) {
            return; // only an unlimited plan has a fair-usage threshold to set
        }
        $this->editingFairUsageId = $id;
        $this->fairUsageValue = (string) ($plan->fair_usage_note ?? '');
    }

    public function saveFairUsage(int $id): void
    {
        $plan = EsimPlan::find($id);
        if (! $plan || $plan->data_mb !== null) {
            return;
        }
        $this->validate(['fairUsageValue' => 'nullable|string|max:300']);
        $plan->fair_usage_note = trim($this->fairUsageValue) ?: null;
        $plan->save();
        EsimCatalogue::flush();
        Auditor::log('esim.fair_usage_note_saved', 'esim_plan', $id, ['has_override' => filled($plan->fair_usage_note)]);
        $this->editingFairUsageId = null;
        $this->fairUsageValue = '';
        $this->flash = 'Fair usage note saved.';
    }

    public function cancelFairUsage(): void
    {
        $this->editingFairUsageId = null;
        $this->fairUsageValue = '';
    }

    // ----------------------------------------------------------------- IMAGES

    public function setImageKind(string $kind): void
    {
        $this->imageKind = in_array($kind, ['country', 'region'], true) ? $kind : 'country';
        $this->reset(['iconUpload', 'detailUpload']);
    }

    public function saveImage(): void
    {
        $rules = [
            'iconUpload' => 'nullable|mimes:webp,png,jpg,jpeg|max:2048',
            'detailUpload' => 'nullable|mimes:webp,png,jpg,jpeg|max:2048',
        ];

        if ($this->imageKind === 'country') {
            $rules['imgCountry'] = 'required|string|size:2';
            $this->validate($rules);
            $code = strtoupper($this->imgCountry);
            $row = EsimCountryImage::firstOrNew(['country_code' => $code]);
            $dir = 'esim-country';
            $label = CountryNames::name($code);
        } else {
            $rules['imgRegion'] = 'required|string';
            $this->validate($rules);
            $slug = $this->imgRegion;
            $row = EsimRegionImage::firstOrNew(['region_slug' => $slug]);
            $dir = 'esim-region';
            $label = EsimRegions::label($slug);
        }

        if (! $this->iconUpload && ! $this->detailUpload && ! $row->exists) {
            $this->flash = 'Upload an icon or a banner image.';

            return;
        }

        if ($this->iconUpload) {
            $row->icon_path = MediaStorage::storePublic($this->iconUpload, $dir);
        }
        if ($this->detailUpload) {
            $row->detail_image_path = MediaStorage::storePublic($this->detailUpload, $dir);
        }
        $row->save();
        EsimCatalogue::flush();

        Auditor::log('esim.image_saved', $this->imageKind, $row->id, [
            'key' => $this->imageKind === 'country' ? $row->country_code : $row->region_slug,
        ]);
        $this->reset(['iconUpload', 'detailUpload']);
        $this->flash = "Saved image for {$label}.";
    }

    public function deleteCountryImage(int $id): void
    {
        EsimCountryImage::where('id', $id)->delete();
        EsimCatalogue::flush();
        Auditor::log('esim.image_deleted', 'country', $id);
        $this->flash = 'Country image removed.';
    }

    public function deleteRegionImage(int $id): void
    {
        EsimRegionImage::where('id', $id)->delete();
        EsimCatalogue::flush();
        Auditor::log('esim.image_deleted', 'region', $id);
        $this->flash = 'Region image removed.';
    }

    // ---------------------------------------------------------------- HELPERS

    private function filteredQuery()
    {
        return EsimPlan::query()
            ->when($this->fProvider !== '', fn ($q) => $q->where('provider', $this->fProvider))
            ->when($this->fCoverage !== '', fn ($q) => $q->where('coverage_type', $this->fCoverage))
            ->when($this->fRegion !== '', fn ($q) => $q->where('region_slug', $this->fRegion))
            ->when($this->fCountry !== '', fn ($q) => $q->whereJsonContains('countries', strtoupper($this->fCountry)))
            ->when($this->fSearch !== '', fn ($q) => $q->where('name', 'like', "%{$this->fSearch}%"));
    }

    private function activeFilterSummary(): array
    {
        return array_filter([
            'provider' => $this->fProvider, 'coverage' => $this->fCoverage,
            'region' => $this->fRegion, 'country' => $this->fCountry, 'search' => $this->fSearch,
        ]);
    }

    public function render(PricingEngine $engine)
    {
        $data = [
            'providers' => CatalogueSyncService::PROVIDERS,
            'flash' => $this->flash,
        ];

        if ($this->section === 'sync') {
            $status = [];
            foreach (CatalogueSyncService::PROVIDERS as $key => $label) {
                $status[$key] = SyncStatus::for($key);
            }
            $data['status'] = $status;
        } elseif ($this->section === 'plans') {
            $plans = $this->filteredQuery()
                ->orderByDesc('is_featured')->orderBy('name')->paginate(15);

            // Precompute profit summaries (admin-only surface, no user exposure).
            $profits = [];
            foreach ($plans as $plan) {
                $profits[$plan->id] = $engine->getProfitSummary($plan, log: false);
            }
            $data += [
                'plans' => $plans,
                'profits' => $profits,
                'coverageTypes' => EsimPlan::COVERAGE_TYPES,
                'regions' => EsimRegions::labels(),
                'aiEnabled' => app(EsimMarginAdvisor::class)->enabled(),
            ];
        } else { // images
            $data += [
                'countryImages' => EsimCountryImage::orderBy('country_code')->get(),
                'regionImages' => EsimRegionImage::orderBy('region_slug')->get(),
                'regionOptions' => array_merge(EsimRegions::labels(), [EsimCatalogue::REGION_OTHER => 'Other regions']),
            ];
        }

        return view('livewire.admin.esim-control-center', $data);
    }
}
