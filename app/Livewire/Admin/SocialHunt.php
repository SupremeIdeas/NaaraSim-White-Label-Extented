<?php

namespace App\Livewire\Admin;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\SocialFollowHandle;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\SocialPlatforms;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Social Hunt (BUILD-6 §C). Manage the platform's own follow handles and
 * the admin-curated brand partners + their handles (add/edit/toggle/remove).
 * BUILD-9 layers self-service subscriptions on top of the same brand tables.
 */
#[Layout('components.layouts.admin')]
class SocialHunt extends Component
{
    use WithFileUploads;

    /** New platform handle form. */
    public array $handle = ['platform' => 'instagram', 'handle_label' => '', 'handle_url' => '', 'credit_reward' => 5, 'verification' => 'self'];

    /** New brand form. */
    public array $brand = ['brand_name' => '', 'short_description' => '', 'background_color' => '#0A6E6E'];

    public $brandImage = null;

    /** New brand-handle form, keyed by brand id. */
    public array $bh = [];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function addHandle(): void
    {
        $data = $this->validate([
            'handle.platform' => 'required|in:'.implode(',', SocialPlatforms::keys()),
            'handle.handle_label' => 'required|string|max:80',
            'handle.handle_url' => 'required|url|max:512',
            'handle.credit_reward' => 'required|numeric|min:0|max:1000',
            'handle.verification' => 'required|in:self,api',
        ])['handle'];

        SocialFollowHandle::create($data + [
            'is_active' => true,
            'sort_order' => (int) SocialFollowHandle::max('sort_order') + 1,
        ]);
        $this->reset('handle');
        $this->handle = ['platform' => 'instagram', 'handle_label' => '', 'handle_url' => '', 'credit_reward' => 5, 'verification' => 'self'];
        Auditor::log('hunt.handle_added');
        $this->saved = 'Handle added.';
    }

    public function toggleHandle(int $id): void
    {
        $h = SocialFollowHandle::findOrFail($id);
        $h->update(['is_active' => ! $h->is_active]);
    }

    public function deleteHandle(int $id): void
    {
        SocialFollowHandle::whereKey($id)->delete();
        Auditor::log('hunt.handle_removed', null, null, ['id' => $id]);
    }

    public function addBrand(): void
    {
        $data = $this->validate([
            'brand.brand_name' => 'required|string|max:120',
            'brand.short_description' => 'nullable|string|max:500',
            'brand.background_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'brandImage' => 'nullable|image|max:1024',
        ]);

        $image = $this->brandImage ? MediaStorage::storePublic($this->brandImage, 'brand-partners') : null;
        BrandPartner::create($data['brand'] + [
            'fallback_image' => $image,
            'is_active' => true,
            // Admin-placed brands are always featured + live, above self-service (BUILD-9 §10.3).
            'is_featured' => true,
            'listing_status' => BrandPartner::STATUS_ACTIVE,
            'sort_order' => (int) BrandPartner::max('sort_order') + 1,
        ]);
        $this->reset('brand', 'brandImage');
        $this->brand = ['brand_name' => '', 'short_description' => '', 'background_color' => '#0A6E6E'];
        Auditor::log('hunt.brand_added');
        $this->saved = 'Brand added.';
    }

    public function toggleBrand(int $id): void
    {
        $b = BrandPartner::findOrFail($id);
        $b->update(['is_active' => ! $b->is_active]);
    }

    public function deleteBrand(int $id): void
    {
        BrandPartner::whereKey($id)->delete(); // cascades to its handles
        Auditor::log('hunt.brand_removed', null, null, ['id' => $id]);
    }

    public function addBrandHandle(int $brandId): void
    {
        $form = $this->bh[$brandId] ?? [];
        $data = validator($form, [
            'platform' => 'required|in:'.implode(',', SocialPlatforms::keys()),
            'handle_label' => 'required|string|max:80',
            'handle_url' => 'required|url|max:512',
            'credit_reward' => 'required|numeric|min:0|max:1000',
            'verification' => 'required|in:self,api',
        ])->validate();

        BrandPartnerHandle::create($data + [
            'brand_partner_id' => $brandId,
            'is_active' => true,
            'sort_order' => (int) BrandPartnerHandle::where('brand_partner_id', $brandId)->max('sort_order') + 1,
        ]);
        unset($this->bh[$brandId]);
        $this->saved = 'Handle added to brand.';
    }

    public function deleteBrandHandle(int $id): void
    {
        BrandPartnerHandle::whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.social-hunt', [
            'platforms' => SocialPlatforms::all(),
            'handles' => SocialFollowHandle::ordered()->get(),
            'brands' => BrandPartner::ordered()->with(['handles' => fn ($q) => $q->ordered()])->get(),
        ]);
    }
}
