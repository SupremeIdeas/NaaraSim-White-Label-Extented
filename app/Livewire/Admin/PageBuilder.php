<?php

namespace App\Livewire\Admin;

use App\Models\PageSection;
use App\Models\PageSectionVersion;
use App\Services\Builder\PageBuilderService;
use App\Support\HtmlSanitizer;
use App\Support\MediaStorage;
use App\Support\PageSections;
use App\Support\SectionLibrary;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Page builder (Section Builder prompt §2–3). Assemble any page from the
 * section library, configure each section, preview it at phone / tablet / desktop
 * widths, then publish. Draft edits stay invisible to the public until publish;
 * every publish snapshots a version the admin can roll back to. Re-authorized
 * every request (super-admin/admin only).
 */
#[Layout('components.layouts.admin')]
class PageBuilder extends Component
{
    use WithFileUploads;

    #[Url(as: 'page')]
    public string $page = 'home';

    public string $newPageKey = '';

    /** The section currently open in the editor (null = none). */
    public ?int $editingId = null;

    /** The open section's config blob, bound to the editor form. */
    public array $config = [];

    /** A pending single-image upload (hero add-image / two-column image). */
    public $upload = null;

    /** Preview viewport: desktop | tablet | mobile. */
    public string $preview = 'desktop';

    public bool $showLibrary = false;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        if (! array_key_exists($this->page, $this->builder()->pages())) {
            $this->page = 'home';
        }
    }

    private function builder(): PageBuilderService
    {
        return app(PageBuilderService::class);
    }

    public function selectPage(string $key): void
    {
        $this->page = $key;
        $this->closeEditor();
    }

    public function createPage(): void
    {
        $slug = \Illuminate\Support\Str::slug($this->newPageKey);
        $this->validate(
            ['newPageKey' => 'required|string|max:60'],
            [],
            ['newPageKey' => 'page name']
        );
        abort_if($slug === '', 422);

        $this->page = $slug;
        $this->newPageKey = '';
        $this->closeEditor();
        $this->dispatch('nx-toast', type: 'success', message: 'Page "'.$slug.'" ready — add sections.');
    }

    public function addSection(string $type): void
    {
        abort_unless(SectionLibrary::has($type), 422);
        $section = $this->builder()->addSection($this->page, $type);
        $this->showLibrary = false;
        $this->edit($section->id);
    }

    public function edit(int $id): void
    {
        $section = $this->section($id);
        $this->editingId = $section->id;
        // Merge onto type defaults so a config saved before a new field existed still binds.
        $this->config = array_merge(SectionLibrary::defaultsFor($section->type), (array) $section->config);
        $this->upload = null;
    }

    public function closeEditor(): void
    {
        $this->editingId = null;
        $this->config = [];
        $this->upload = null;
    }

    /** Hero: switch the preset (re-applies its mode/scheme/align defaults, keeps copy). */
    public function applyPreset(string $preset): void
    {
        $this->config = SectionLibrary::applyHeroPreset($this->config, $preset);
    }

    /** Hero: switch the background mode without changing the preset. */
    public function setMode(string $mode): void
    {
        if (in_array($mode, ['animation', 'image', 'images', 'static'], true)) {
            $this->config['mode'] = $mode;
        }
    }

    /** Upload one image → append to the hero's image list (or set the two-column image). */
    public function addImage(): void
    {
        $section = $this->section($this->editingId);
        $this->validate(['upload' => 'required|image|mimes:webp,jpg,jpeg,png|max:1500']);

        $url = MediaStorage::storePublic($this->upload, 'page-sections');
        $this->upload = null;

        if ($section->type === 'hero') {
            $images = array_values((array) ($this->config['images'] ?? []));
            $images[] = $url;
            $this->config['images'] = array_slice($images, 0, 8);
        } else {
            $this->config['image'] = $url;
        }
    }

    public function removeImage(int $index): void
    {
        $images = array_values((array) ($this->config['images'] ?? []));
        unset($images[$index]);
        $this->config['images'] = array_values($images);
    }

    public function clearImage(): void
    {
        $this->config['image'] = '';
    }

    /* ---- Repeater helpers (bento cards, faq items, testimonials, logos) ---- */

    public function addRepeaterItem(): void
    {
        $section = $this->section($this->editingId);
        if (! $r = SectionLibrary::repeaterFor($section->type)) {
            return;
        }
        $items = array_values((array) ($this->config[$r['field']] ?? []));
        $items[] = $r['template'];
        $this->config[$r['field']] = array_slice($items, 0, 12);
    }

    public function removeRepeaterItem(int $index): void
    {
        $section = $this->section($this->editingId);
        if (! $r = SectionLibrary::repeaterFor($section->type)) {
            return;
        }
        $items = array_values((array) ($this->config[$r['field']] ?? []));
        unset($items[$index]);
        $this->config[$r['field']] = array_values($items);
    }

    /** Upload an image into a repeater row (card/testimonial photo/logo). */
    public function uploadRepeaterImage(int $index): void
    {
        $section = $this->section($this->editingId);
        $r = SectionLibrary::repeaterFor($section->type);
        if (! $r || ! $r['imageKey']) {
            return;
        }
        $this->validate(['upload' => 'required|image|mimes:webp,jpg,jpeg,png|max:1500']);
        $url = MediaStorage::storePublic($this->upload, 'page-sections');
        $this->upload = null;

        $items = array_values((array) ($this->config[$r['field']] ?? []));
        if (isset($items[$index])) {
            $items[$index][$r['imageKey']] = $url;
            $this->config[$r['field']] = $items;
        }
    }

    public function saveSection(): void
    {
        $section = $this->section($this->editingId);
        $config = $this->config;

        // Per-type normalisation + light validation. Copy is free-form; we cap
        // lengths and (critically) sanitize any custom HTML.
        if ($section->type === 'hero') {
            $this->validate([
                'config.headline' => 'required|string|max:120',
                'config.eyebrow' => 'nullable|string|max:80',
                'config.subheadline' => 'nullable|string|max:280',
                'config.preset' => 'required|string',
                'config.mode' => 'required|in:animation,image,images,static',
                'config.scheme' => 'required|in:brand,light,dark',
                'config.align' => 'required|in:left,center',
            ]);
        } elseif ($section->type === 'two_column') {
            $this->validate([
                'config.headline' => 'required|string|max:120',
                'config.body' => 'nullable|string|max:600',
                'config.image_side' => 'required|in:left,right',
                'config.bg' => 'required|in:transparent,tint,dark',
            ]);
        } elseif ($section->type === 'storytelling') {
            $this->validate([
                'config.tone' => 'required|in:auto,on-dark',
                'config.heading' => 'nullable|string|max:120',
                'config.subheading' => 'nullable|string|max:280',
                'config.slides.*.title' => 'nullable|string|max:120',
                'config.slides.*.body' => 'nullable|string|max:600',
                'config.slides.*.modal_body' => 'nullable|string|max:1200',
            ]);
        } elseif ($section->type === 'custom_html') {
            $config['html'] = HtmlSanitizer::clean($config['html'] ?? '');
        }

        $this->builder()->updateConfig($section, $config);
        $this->config = array_merge(SectionLibrary::defaultsFor($section->type), $config);
        $this->dispatch('nx-toast', type: 'success', message: 'Section saved.');
    }

    public function moveUp(int $id): void
    {
        $this->builder()->move($this->section($id), 'up');
    }

    public function moveDown(int $id): void
    {
        $this->builder()->move($this->section($id), 'down');
    }

    public function toggle(int $id): void
    {
        $this->builder()->toggle($this->section($id));
    }

    public function remove(int $id): void
    {
        $this->builder()->remove($this->section($id));
        if ($this->editingId === $id) {
            $this->closeEditor();
        }
    }

    /** Drag reorder from the client (array of section ids in the new order). */
    public function reorder(array $ids): void
    {
        $this->builder()->reorder($this->page, array_map('intval', $ids));
    }

    public function publish(): void
    {
        $this->builder()->publish($this->page, Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Page published — it is now live.');
    }

    public function rollback(int $versionId): void
    {
        $version = PageSectionVersion::where('page_key', $this->page)->findOrFail($versionId);
        $this->builder()->rollback($version, Auth::user());
        $this->closeEditor();
        $this->dispatch('nx-toast', type: 'success', message: 'Rolled back and re-published.');
    }

    /** Load a section, scoped to the current page (so a stale id can't cross pages). */
    private function section(?int $id): PageSection
    {
        abort_if($id === null, 404);

        return PageSection::where('page_key', $this->page)->findOrFail($id);
    }

    public function render()
    {
        $sections = PageSection::where('page_key', $this->page)
            ->orderBy('sort_order')->orderBy('id')->get();

        $editing = $this->editingId ? $sections->firstWhere('id', $this->editingId) : null;

        return view('livewire.admin.page-builder', [
            'sections' => $sections,
            'editing' => $editing,
            'previewSections' => PageSections::draft($this->page),
            'library' => SectionLibrary::types(),
            'heroPresets' => SectionLibrary::heroPresets(),
            'pages' => $this->builder()->pages(),
            'versions' => PageSectionVersion::where('page_key', $this->page)
                ->latest('id')->limit(15)->get(),
            'hasLive' => PageSections::hasLive($this->page),
        ]);
    }
}
