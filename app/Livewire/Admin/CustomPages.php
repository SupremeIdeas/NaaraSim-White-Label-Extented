<?php

namespace App\Livewire\Admin;

use App\Models\CustomPage;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Pages (CMS). Create/edit/delete custom-HTML pages served at /p/{slug}.
 * Admin-only; the raw HTML is trusted operator content (the site CSP still blocks
 * inline scripts, protecting visitors).
 */
#[Layout('components.layouts.admin')]
class CustomPages extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public string $slug = '';

    public string $html = '';

    public string $meta = '';

    public bool $isPublished = false;

    public bool $inNav = false;

    public bool $fullWidth = false;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function newPage(): void
    {
        $this->reset('editingId', 'title', 'slug', 'html', 'meta', 'isPublished', 'inNav', 'fullWidth', 'saved');
    }

    public function edit(int $id): void
    {
        $page = CustomPage::findOrFail($id);
        $this->editingId = $page->id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->html = (string) $page->html;
        $this->meta = (string) $page->meta_description;
        $this->isPublished = $page->is_published;
        $this->inNav = $page->in_nav;
        $this->fullWidth = $page->full_width;
        $this->saved = null;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        // Derive a slug from the title when left blank.
        if (trim($this->slug) === '') {
            $this->slug = Str::slug($this->title);
        }
        $this->slug = Str::slug($this->slug);

        $this->validate([
            'title' => 'required|string|max:120',
            'slug' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/',
                Rule::notIn(CustomPage::RESERVED),
                Rule::unique('custom_pages', 'slug')->ignore($this->editingId),
            ],
            'html' => 'nullable|string',
            'meta' => 'nullable|string|max:200',
        ], [
            'slug.not_in' => 'That slug is reserved by a built-in page. Choose another.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
        ]);

        $data = [
            'slug' => $this->slug,
            'title' => $this->title,
            'html' => $this->html,
            'meta_description' => $this->meta ?: null,
            'is_published' => $this->isPublished,
            'in_nav' => $this->inNav,
            'full_width' => $this->fullWidth,
        ];

        $page = $this->editingId
            ? tap(CustomPage::findOrFail($this->editingId))->update($data)
            : CustomPage::create($data);
        $this->editingId = $page->id;

        Auditor::log('custom_page.saved', 'CustomPage', $page->id, ['slug' => $page->slug, 'published' => $page->is_published]);
        $this->saved = 'Page saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Page saved.');
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $page = CustomPage::findOrFail($id);
        $slug = $page->slug;
        $page->delete();

        Auditor::log('custom_page.deleted', 'CustomPage', $id, ['slug' => $slug]);
        if ($this->editingId === $id) {
            $this->newPage();
        }
        $this->dispatch('nx-toast', type: 'success', message: 'Page deleted.');
    }

    public function render()
    {
        return view('livewire.admin.custom-pages', [
            'pages' => CustomPage::orderByDesc('updated_at')->get(),
        ]);
    }
}
