<?php

namespace App\Livewire\Admin;

use App\Jobs\BroadcastAnnouncementJob;
use App\Models\Announcement;
use App\Models\Coupon;
use App\Support\Auditor;
use App\Support\DeepLinkLibrary;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin → Announcements & offers (owner request). Compose an offer or update
 * once and push it to every active user's notification bell, with an optional
 * one-click coupon to claim. Money-safety: an attached coupon must be a real,
 * live coupon (the discount itself is still MarginGuard-clamped at checkout, so
 * a broadcast can never create an unprofitable sale).
 *
 * Re-authorized on every request via booted() — Livewire method calls hit the
 * shared update endpoint where the route's role middleware is not re-run.
 */
#[Layout('components.layouts.admin')]
class Announcements extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $title = '';

    public string $body = '';

    public string $icon = 'gift';

    /** Tier 5 #11 Phase A1 — which presentation style this announcement uses. */
    public string $style = 'banner_hero';

    /** banner_hero's wide banner image (dashboard-hero dimensions, 1600x800). */
    public $image = null;

    /** dark_feature's smaller inset preview image. */
    public $featureImage = null;

    /** dark_feature's bullet-point feature callouts, one per line. */
    public string $bulletsText = '';

    public string $cta_label = '';

    public string $cta_url = '';

    /** dark_feature's lighter-weight secondary link (e.g. "View all changelogs"). */
    public string $secondary_label = '';

    public string $secondary_url = '';

    public string $coupon_code = '';

    public ?string $sent = null;

    /** Icons offered for an announcement (SVG sprite names — no emoji). */
    public const ICONS = ['gift', 'tag', 'wallet', 'signal', 'bell', 'shield-check'];

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function send(): void
    {
        $data = $this->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'icon' => 'required|in:'.implode(',', self::ICONS),
            'style' => 'required|in:'.implode(',', Announcement::STYLES),
            'image' => 'nullable|image|mimes:jpg,jpeg,webp|max:2048',
            'featureImage' => 'nullable|image|mimes:jpg,jpeg,webp|max:2048',
            'bulletsText' => 'nullable|string|max:1000',
            'cta_label' => 'nullable|string|max:40',
            'cta_url' => 'nullable|url|max:300',
            'secondary_label' => 'nullable|string|max:40',
            'secondary_url' => 'nullable|url|max:300',
            'coupon_code' => 'nullable|string|max:40',
        ]);

        // A coupon, if given, must exist and be live — you can't broadcast a dead
        // or fictional code. The discount stays MarginGuard-clamped at checkout.
        $code = strtoupper(trim($this->coupon_code));
        if ($code !== '') {
            $coupon = Coupon::whereRaw('UPPER(code) = ?', [$code])->first();
            if (! $coupon || ! $coupon->isRedeemable()) {
                $this->addError('coupon_code', 'That coupon is not a live, redeemable code.');

                return;
            }
        }

        // Tier 5 #11 Phase A1 — bullets only apply to the dark_feature style.
        $bullets = $data['style'] === 'dark_feature'
            ? array_values(array_filter(array_map('trim', explode("\n", (string) $data['bulletsText']))))
            : [];

        $announcement = Announcement::create([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'icon' => $data['icon'],
            'style' => $data['style'],
            'image_path' => $this->image ? MediaStorage::storePublic($this->image, 'announcements') : null,
            'feature_image_path' => $this->featureImage ? MediaStorage::storePublic($this->featureImage, 'announcements') : null,
            'bullets' => $bullets ?: null,
            'cta_label' => trim($data['cta_label']) ?: null,
            'cta_url' => trim($data['cta_url']) ?: null,
            'secondary_label' => trim($data['secondary_label']) ?: null,
            'secondary_url' => trim($data['secondary_url']) ?: null,
            'coupon_code' => $code ?: null,
            'audience' => 'all',
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        BroadcastAnnouncementJob::dispatch($announcement->id);
        Auditor::log('announcement.sent', Announcement::class, $announcement->id, ['title' => $announcement->title]);

        $this->reset(
            'title', 'body', 'image', 'featureImage', 'bulletsText',
            'cta_label', 'cta_url', 'secondary_label', 'secondary_url',
            'coupon_code',
        );
        $this->icon = 'gift';
        $this->style = 'banner_hero';
        $this->sent = 'Announcement is being delivered to every active user’s notifications.';
    }

    public function render()
    {
        return view('livewire.admin.announcements', [
            'announcements' => Announcement::with('author')->latest()->paginate(10),
            'icons' => self::ICONS,
            'styles' => Announcement::STYLES,
            'deepLinks' => DeepLinkLibrary::all(),
        ]);
    }
}
