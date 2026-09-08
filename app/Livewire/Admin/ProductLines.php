<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\ProductLineSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Product lines (BLUEPRINT-batch1-sections §4). A repeater over the six
 * Naara product lines: add/reorder/edit each product, its ordered body blocks
 * (the 3-beat arc) and its modal gallery, with a hero-image upload. This is the
 * mechanism that makes the homepage product storytelling non-hardcoded.
 *
 * super_admin/admin only, re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class ProductLines extends Component
{
    use WithFileUploads;

    /** The working copy of the product list. */
    public array $products = [];

    /** Per-product hero image upload, keyed by product index. */
    public array $heroUploads = [];

    /** Per-product new gallery image upload, keyed by product index. */
    public array $galleryUploads = [];

    public ?string $saved = null;

    public ?string $uploadError = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->products = ProductLineSettings::products();
    }

    public function addProduct(): void
    {
        $this->products[] = [
            'slug' => 'product-'.\Illuminate\Support\Str::random(5),
            'icon' => 'signal', 'hero_image' => '', 'is_draft' => true,
            'eyebrow' => '', 'title' => 'New product', 'summary' => '',
            'cta_label' => 'Learn more', 'cta_route' => 'dashboard',
            'modal_gallery' => [], 'modal_blocks' => [],
        ];
    }

    public function removeProduct(int $i): void
    {
        unset($this->products[$i]);
        $this->products = array_values($this->products);
    }

    public function moveProduct(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (! isset($this->products[$i], $this->products[$j])) {
            return;
        }
        [$this->products[$i], $this->products[$j]] = [$this->products[$j], $this->products[$i]];
        $this->products = array_values($this->products);
    }

    public function addBlock(int $i): void
    {
        $this->products[$i]['modal_blocks'][] = ['heading' => '', 'text' => ''];
    }

    public function removeBlock(int $i, int $j): void
    {
        unset($this->products[$i]['modal_blocks'][$j]);
        $this->products[$i]['modal_blocks'] = array_values($this->products[$i]['modal_blocks']);
    }

    public function removeGalleryImage(int $i, int $k): void
    {
        unset($this->products[$i]['modal_gallery'][$k]);
        $this->products[$i]['modal_gallery'] = array_values($this->products[$i]['modal_gallery']);
    }

    /** A hero upload immediately stores + wires the URL for that product. */
    public function updatedHeroUploads($value, $key): void
    {
        $this->storeUpload((int) $key, $value, fn ($url) => $this->products[(int) $key]['hero_image'] = $url);
    }

    /** A gallery upload appends a stored URL to that product's gallery. */
    public function updatedGalleryUploads($value, $key): void
    {
        $this->storeUpload((int) $key, $value, function ($url) use ($key) {
            $this->products[(int) $key]['modal_gallery'][] = $url;
        });
        $this->galleryUploads[(int) $key] = null;
    }

    private function storeUpload(int $i, $file, callable $assign): void
    {
        $this->uploadError = null;
        if (! isset($this->products[$i]) || ! $file) {
            return;
        }
        if (! $file->isValid() || ! in_array(strtolower($file->getClientOriginalExtension()), ['webp', 'png', 'jpg', 'jpeg'], true) || $file->getSize() > 2 * 1024 * 1024) {
            $this->uploadError = 'Use a WebP, PNG or JPG under 2 MB.';

            return;
        }
        $assign(MediaStorage::storePublic($file, 'product-lines'));
    }

    public function save(): void
    {
        $this->validate([
            'products' => 'array|min:1',
            'products.*.title' => 'required|string|max:80',
            'products.*.summary' => 'nullable|string|max:600',
            'products.*.cta_route' => 'nullable|string|max:120',
            'products.*.hero_image' => 'nullable|string|max:2048',
            'products.*.modal_blocks.*.heading' => 'nullable|string|max:120',
            'products.*.modal_blocks.*.text' => 'nullable|string|max:800',
        ]);

        // Normalise: drop empty blocks, keep known keys.
        $clean = array_map(function ($p) {
            $p['modal_blocks'] = array_values(array_filter($p['modal_blocks'] ?? [], fn ($b) => filled($b['heading'] ?? '') || filled($b['text'] ?? '')));
            $p['modal_gallery'] = array_values(array_filter($p['modal_gallery'] ?? [], fn ($u) => filled($u)));
            $p['is_draft'] = (bool) ($p['is_draft'] ?? false);

            return $p;
        }, $this->products);

        ProductLineSettings::save($clean);
        $this->products = ProductLineSettings::products();
        Auditor::log('product_lines.updated', null, null, ['count' => count($clean)]);
        $this->saved = 'saved';
        $this->dispatch('nx-toast', type: 'success', message: 'Product lines saved — live on the homepage.');
    }

    public function restoreDefaults(): void
    {
        ProductLineSettings::save(ProductLineSettings::defaults());
        $this->products = ProductLineSettings::products();
        $this->saved = 'restored';
        $this->dispatch('nx-toast', type: 'success', message: 'Restored the default product lines.');
    }

    public function render()
    {
        return view('livewire.admin.product-lines');
    }
}
