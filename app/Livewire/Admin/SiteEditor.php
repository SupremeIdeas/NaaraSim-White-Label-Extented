<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Pages (Module 27). No-code editor for the public marketing pages:
 * pick a page, edit every section's text, upload a section image (hero
 * backdrops etc.), show/hide sections, and reorder them. Overrides are stored
 * per page and merged over the shipped brand copy — "Reset section" clears the
 * overrides and returns to the original copy.
 */
#[Layout('components.layouts.admin')]
class SiteEditor extends Component
{
    use WithFileUploads;

    public string $page = 'home';

    /** section => field => value (working copy shown in the form). */
    public array $sections = [];

    /** section key an image is being uploaded for. */
    public $imageUpload = null;

    public ?string $imageSection = null;

    /** field key an inline artwork (`*_image`) is being uploaded for. */
    public ?string $imageField = null;

    public ?string $saved = null;

    public function mount(): void
    {
        $this->loadPage();
    }

    public function updatedPage(): void
    {
        $this->loadPage();
        $this->saved = null;
    }

    private function loadPage(): void
    {
        if (! in_array($this->page, SiteContent::editablePages(), true)) {
            $this->page = 'home';
        }
        $this->sections = SiteContent::page($this->page, includeHidden: true);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        // Persist only differences from the shipped defaults (+ the reserved
        // keys), so resetting stays possible and the row stays small.
        $defaults = SiteContent::defaults()[$this->page] ?? [];
        $overrides = [];

        foreach ($this->sections as $key => $fields) {
            // A reused/copied portable section has no default on THIS page, so
            // its full field set is its override; diff against its canonical
            // portable defaults instead so it still stores minimally.
            $sectionDefaults = $defaults[$key] ?? (in_array($key, SiteContent::PORTABLE_SECTIONS, true) ? SiteContent::portableDefaults($key) : []);

            $sectionOverride = [];
            foreach ($fields as $field => $value) {
                if (in_array($field, ['visible', 'order', 'image', '_copied'], true)) {
                    continue;
                }
                if (($sectionDefaults[$field] ?? null) !== $value) {
                    $sectionOverride[$field] = $value;
                }
            }
            $sectionOverride['visible'] = (bool) ($fields['visible'] ?? true);
            $sectionOverride['order'] = (int) ($fields['order'] ?? 0);
            if (! empty($fields['image'])) {
                $sectionOverride['image'] = $fields['image'];
            }
            $overrides[$key] = $sectionOverride;
        }

        SiteContent::saveOverrides($this->page, $overrides);
        Auditor::log('site.page_updated', null, null, ['page' => $this->page]);

        $this->saved = 'Page saved — live immediately.';
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($this->page).' page saved.');
        $this->loadPage();
    }

    public function moveSection(string $key, int $direction): void
    {
        $keys = array_keys($this->sections);
        $index = array_search($key, $keys, true);
        $swap = $index + $direction;
        if ($index === false || $swap < 0 || $swap >= count($keys)) {
            return;
        }

        // Renumber sequentially, then swap the two positions.
        $position = 0;
        foreach ($this->sections as $k => $_) {
            $this->sections[$k]['order'] = $position++;
        }
        $other = $keys[$swap];
        [$this->sections[$key]['order'], $this->sections[$other]['order']] =
            [$this->sections[$other]['order'], $this->sections[$key]['order']];

        uasort($this->sections, fn ($a, $b) => $a['order'] <=> $b['order']);
    }

    public function uploadImage(string $section): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate(['imageUpload' => 'required|image|max:2048']);

        $url = MediaStorage::storePublic($this->imageUpload, 'site');
        $this->sections[$section]['image'] = $url;
        $this->imageUpload = null;
        $this->imageSection = null;
    }

    public function removeImage(string $section): void
    {
        $this->sections[$section]['image'] = '';
    }

    /**
     * Swap an inline artwork field (any key ending in `_image`, e.g. the three
     * "Connected in Three Steps" step renders). Stored as a normal field value,
     * so it persists through the same override diff as the copy fields and
     * "Reset section" returns it to the shipped default.
     */
    public function uploadFieldImage(string $section, string $field): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(str_ends_with($field, '_image'), 403);

        $this->validate(['imageUpload' => 'required|image|max:2048']);

        $this->sections[$section][$field] = MediaStorage::storePublic($this->imageUpload, 'site');
        $this->imageUpload = null;
        $this->imageSection = null;
        $this->imageField = null;
    }

    public function removeFieldImage(string $section, string $field): void
    {
        abort_unless(str_ends_with($field, '_image'), 403);

        // Back to the shipped default artwork rather than a blank slot. A reused
        // portable section has no default on this page, so fall back to its
        // canonical portable default.
        $this->sections[$section][$field] = SiteContent::defaults()[$this->page][$section][$field]
            ?? (in_array($section, SiteContent::PORTABLE_SECTIONS, true) ? (SiteContent::portableDefaults($section)[$field] ?? '') : '');
    }

    public function resetSection(string $key): void
    {
        $defaults = SiteContent::defaults()[$this->page][$key]
            ?? (in_array($key, SiteContent::PORTABLE_SECTIONS, true) ? SiteContent::portableDefaults($key) : []);
        $order = $this->sections[$key]['order'] ?? 0;
        $copied = $this->sections[$key]['_copied'] ?? false;
        $this->sections[$key] = array_merge($defaults, array_filter([
            'visible' => true, 'order' => $order, 'image' => '', '_copied' => $copied ?: null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Copy a portable section from the current page onto another page (reusable
     * sections). The current (working) field values are snapshotted so any
     * unsaved edits carry over. Only portable sections offer this action.
     */
    public function copyTo(string $key, string $target): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $fields = $this->sections[$key] ?? [];
        $ok = SiteContent::copySection($key, $fields, $target, (string) ($fields['image'] ?? ''));

        if ($ok) {
            Auditor::log('site.section_copied', null, null, ['section' => $key, 'from' => $this->page, 'to' => $target]);
            $this->dispatch('nx-toast', type: 'success', message: 'Section copied to the '.$target.' page.');
        } else {
            $this->dispatch('nx-toast', type: 'error', message: 'That section can’t be reused on another page.');
        }
    }

    /**
     * Remove a reused/copied section from the current page. Native (shipped)
     * sections are hidden via the visible toggle, not removed — only a copied
     * instance can be deleted.
     */
    public function removeSection(string $key): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        if (! ($this->sections[$key]['_copied'] ?? false)) {
            return;
        }

        unset($this->sections[$key]);
        $this->save();
        $this->dispatch('nx-toast', type: 'success', message: 'Reused section removed.');
    }

    public function render()
    {
        return view('livewire.admin.site-editor', ['pages' => SiteContent::editablePages()]);
    }
}
