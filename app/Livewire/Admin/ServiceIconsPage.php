<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\ServiceIcons;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Service icons (Module 27.5, owner request). Every number/OTP service
 * shows a logo out of the box (bundled brand marks); this page lets the admin
 * upload an OFFICIAL logo for any service — overriding the bundled mark, and
 * covering services the live provider APIs don't return artwork for. New
 * service slugs can be added freely (e.g. a niche dating app 5sim offers).
 */
#[Layout('components.layouts.admin')]
class ServiceIconsPage extends Component
{
    use WithFileUploads;

    public $upload = null;

    public string $uploadSlug = '';

    public string $newSlug = '';

    public ?string $saved = null;

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $slug = ServiceIcons::slug($this->uploadSlug !== '' ? $this->uploadSlug : $this->newSlug);

        // Explicitly accept WebP, SVG and PNG (plus JPG). SVG is safe here
        // because service icons always render via <img src> (an <img>-loaded
        // SVG never executes scripts).
        $this->validate([
            'upload' => 'required|file|mimes:webp,svg,png,jpg,jpeg|max:1024',
        ]);
        if ($slug === '') {
            $this->addError('newSlug', 'Enter the service name (e.g. okcupid).');

            return;
        }

        $url = MediaStorage::storePublic($this->upload, 'service-icons');
        ServiceIcons::saveOverride($slug, $url);
        Auditor::log('service_icons.updated', null, null, ['service' => $slug]);

        $this->reset('upload', 'uploadSlug', 'newSlug');
        $this->saved = 'Logo saved for '.$slug.'.';
        $this->dispatch('nx-toast', type: 'success', message: 'Service logo saved.');
    }

    public function remove(string $slug): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        ServiceIcons::removeOverride($slug);
        $this->saved = 'Override removed — back to the built-in mark.';
    }

    /** Only show services still on the letter-avatar fallback (needs an icon). */
    public bool $onlyMissing = false;

    public function render()
    {
        $overrides = ServiceIcons::overrides();

        // Every service the catalogue offers, plus bundled + custom slugs, each
        // tagged with its current icon state so the admin can see — and fix —
        // exactly which services still lack a real logo.
        $slugs = collect(\App\Support\NumberCatalogue::services())->keys()
            ->merge(ServiceIcons::BUNDLED)
            ->merge(array_keys($overrides))
            ->map(fn ($s) => ServiceIcons::slug($s))
            ->unique()->sort()->values();

        $rows = $slugs->map(fn ($slug) => [
            'slug' => $slug,
            'state' => isset($overrides[$slug]) ? 'custom'
                : (in_array($slug, ServiceIcons::BUNDLED, true) ? 'bundled' : 'missing'),
        ]);

        $missingCount = $rows->where('state', 'missing')->count();
        if ($this->onlyMissing) {
            $rows = $rows->where('state', 'missing')->values();
        }

        return view('livewire.admin.service-icons', [
            'rows' => $rows->values(),
            'overrides' => $overrides,
            'missingCount' => $missingCount,
        ]);
    }
}
