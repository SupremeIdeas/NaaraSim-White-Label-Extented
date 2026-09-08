<?php

namespace App\Livewire\Admin;

use App\Models\PageSection;
use App\Services\Builder\PageBuilderService;
use App\Services\Marketing\MarketingCopywriter;
use App\Support\Auditor;
use App\Support\CopyFields;
use App\Support\MarketingBrief;
use App\Support\PageSections;
use App\Support\SectionLibrary;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Marketing Copy Studio — the CMS-assisted, Claude-powered copy populator.
 * Fill the brand brief once (name defaults to the white-label word), pick a
 * builder page, and generate several on-brand copy variations for any section
 * (or the whole page) with one click; apply the one you like into the section's
 * draft, then publish in Page Builder as usual.
 *
 * Only ever rewrites human-readable copy (via CopyFields) — never links, images
 * or structure. Super-admin / admin only.
 */
#[Layout('components.layouts.admin')]
class MarketingCopyStudio extends Component
{
    public string $brand = '';

    public string $one_liner = '';

    public string $audience = '';

    public string $tone = '';

    public string $keywords = '';

    public string $page = 'home';

    /** section id => list of variations, each ['config' => [...], 'preview' => [path => text]]. */
    public array $variations = [];

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $brief = MarketingBrief::get();
        $this->brand = $brief['brand'];
        $this->one_liner = $brief['one_liner'];
        $this->audience = $brief['audience'];
        $this->tone = $brief['tone'];
        $this->keywords = $brief['keywords'];
    }

    public function saveBrief(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'brand' => 'required|string|max:60',
            'one_liner' => 'nullable|string|max:300',
            'audience' => 'nullable|string|max:300',
            'tone' => 'nullable|string|max:300',
            'keywords' => 'nullable|string|max:300',
        ]);

        MarketingBrief::save([
            'brand' => $this->brand, 'one_liner' => $this->one_liner,
            'audience' => $this->audience, 'tone' => $this->tone, 'keywords' => $this->keywords,
        ]);
        Auditor::log('marketing.brief_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Brand brief saved — future copy will use it.');
    }

    public function selectPage(string $key): void
    {
        if (array_key_exists($key, app(PageBuilderService::class)->pages())) {
            $this->page = $key;
            $this->variations = [];
        }
    }

    /** Generate variations for one section. */
    public function generate(int $sectionId, MarketingCopywriter $writer): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->error = null;

        if (! $writer->enabled()) {
            $this->error = 'Add an Anthropic API key (Integrations) to generate copy.';

            return;
        }

        $section = PageSection::where('page_key', $this->page)->find($sectionId);
        if ($section === null) {
            return;
        }

        try {
            $configs = $writer->sectionVariations($section->type, (array) $section->config, 3);
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'The copywriter could not be reached. Try again in a moment.';

            return;
        }

        if ($configs === []) {
            $this->dispatch('nx-toast', type: 'info', message: 'This section has no editable copy to rewrite.');

            return;
        }

        $this->variations[$sectionId] = array_map(fn ($config) => [
            'config' => $config,
            'preview' => CopyFields::extract($config),
        ], $configs);
    }

    /** Apply a chosen variation into the section's draft config. */
    public function apply(int $sectionId, int $index): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $variation = $this->variations[$sectionId][$index] ?? null;
        $section = PageSection::where('page_key', $this->page)->find($sectionId);
        if ($variation === null || $section === null) {
            return;
        }

        $section->update(['config' => $variation['config']]);
        PageSections::flush($this->page);
        Auditor::log('marketing.copy_applied', 'PageSection', $sectionId, ['page' => $this->page]);
        unset($this->variations[$sectionId]);
        $this->dispatch('nx-toast', type: 'success',
            message: 'Copy applied to the draft — publish it in Page Builder to go live.');
    }

    public function discard(int $sectionId): void
    {
        unset($this->variations[$sectionId]);
    }

    public function render()
    {
        $sections = PageSection::where('page_key', $this->page)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($s) => SectionLibrary::has($s->type))
            ->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->type,
                'label' => SectionLibrary::types()[$s->type]['label'] ?? ucfirst($s->type),
                'copy' => CopyFields::extract((array) $s->config),
            ])
            ->values();

        return view('livewire.admin.marketing-copy-studio', [
            'pages' => app(PageBuilderService::class)->pages(),
            'sections' => $sections,
            'aiEnabled' => app(MarketingCopywriter::class)->enabled(),
        ]);
    }
}
