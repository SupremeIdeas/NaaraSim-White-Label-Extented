<?php

namespace App\Livewire\Admin;

use App\Models\Banner;
use App\Models\Coupon;
use App\Support\Auditor;
use App\Support\Banners as BannerCache;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Banners (Module 31). Upload promo artwork per placement zone —
 * dashboard-home carousel, mobile "More" menu, account page — sized for its
 * zone (JPG/WebP), optionally linking out or carrying a coupon code. An
 * optional second upload serves small screens so every device gets sharp,
 * correctly-cropped artwork.
 */
#[Layout('components.layouts.admin')]
class Banners extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $placement = 'dashboard_home';

    public $image = null;          // desktop / default artwork (jpg/webp) — also the video poster

    public $image_mobile = null;   // optional small-screen artwork

    public $video = null;          // optional motion video (mp4/webm, ≤10 MB) — plays over the poster

    public string $link_url = '';

    public $coupon_id = '';

    public $sort_order = 0;

    public $starts_at = null;

    public $ends_at = null;

    public ?string $saved = null;

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'title' => 'required|string|max:120',
            'placement' => 'required|in:'.implode(',', array_keys(Banner::PLACEMENTS)),
            'image' => 'required|file|mimes:jpg,jpeg,webp|max:2048',
            'image_mobile' => 'nullable|file|mimes:jpg,jpeg,webp|max:2048',
            'video' => array_merge(['nullable'], MediaStorage::videoUploadRules()),
            'link_url' => 'nullable|string|max:500',
            'coupon_id' => 'nullable|exists:coupons,id',
            'sort_order' => 'required|integer|min:0|max:999',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ], [
            'image.mimes' => 'Banner artwork must be JPG or WebP.',
            'image_mobile.mimes' => 'Mobile artwork must be JPG or WebP.',
            'video.mimetypes' => 'Banner video must be an MP4 or WebM file.',
            'video.max' => 'Keep the banner video under 10 MB so it loads fast.',
        ]);

        // A link must be a same-app path (/...) or a full http(s) URL — nothing
        // script-y can ever end up in an href.
        $link = trim($this->link_url);
        if ($link !== '' && ! preg_match('#^(/|https?://)#i', $link)) {
            $this->addError('link_url', 'Use a full https:// URL or an in-app path starting with /.');

            return;
        }

        $banner = Banner::create([
            'title' => trim($this->title),
            'placement' => $this->placement,
            'image_url' => MediaStorage::storePublic($this->image, 'banners'),
            'image_url_mobile' => $this->image_mobile ? MediaStorage::storePublic($this->image_mobile, 'banners') : null,
            'video_url' => $this->video ? MediaStorage::storePublic($this->video, 'banners') : null,
            'link_url' => $link ?: null,
            'coupon_id' => $this->coupon_id ?: null,
            'sort_order' => (int) $this->sort_order,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
            'is_active' => true,
        ]);

        Auditor::log('banner.created', Banner::class, $banner->id, ['id' => $banner->id, 'placement' => $banner->placement]);
        $this->reset('title', 'image', 'image_mobile', 'video', 'link_url', 'coupon_id', 'starts_at', 'ends_at');
        $this->sort_order = 0;
        $this->saved = 'Banner published to the '.Banner::PLACEMENTS[$banner->placement][0].' zone.';
        $this->dispatch('nx-toast', type: 'success', message: 'Banner published.');
    }

    public function toggle(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $banner = Banner::findOrFail($id);
        $banner->update(['is_active' => ! $banner->is_active]);
        Auditor::log('banner.toggled', Banner::class, $banner->id, ['id' => $id, 'active' => $banner->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $banner = Banner::findOrFail($id);
        Auditor::log('banner.deleted', Banner::class, $banner->id, ['id' => $id]);
        $banner->delete();
    }

    public function render()
    {
        BannerCache::flush(); // admin always sees fresh state

        return view('livewire.admin.banners', [
            'banners' => Banner::with('coupon')->orderBy('placement')->orderBy('sort_order')->get(),
            'couponOptions' => Coupon::where('is_active', true)->orderBy('code')->get(),
            'placements' => Banner::PLACEMENTS,
        ]);
    }
}
