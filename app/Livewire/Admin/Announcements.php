<?php

namespace App\Livewire\Admin;

use App\Jobs\BroadcastAnnouncementJob;
use App\Models\Announcement;
use App\Models\Coupon;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
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
    use WithPagination;

    public string $title = '';

    public string $body = '';

    public string $icon = 'gift';

    public string $cta_label = '';

    public string $cta_url = '';

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
            'cta_label' => 'nullable|string|max:40',
            'cta_url' => 'nullable|url|max:300',
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

        $announcement = Announcement::create([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'icon' => $data['icon'],
            'cta_label' => trim($data['cta_label']) ?: null,
            'cta_url' => trim($data['cta_url']) ?: null,
            'coupon_code' => $code ?: null,
            'audience' => 'all',
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        BroadcastAnnouncementJob::dispatch($announcement->id);
        Auditor::log('announcement.sent', Announcement::class, $announcement->id, ['title' => $announcement->title]);

        $this->reset('title', 'body', 'cta_label', 'cta_url', 'coupon_code');
        $this->icon = 'gift';
        $this->sent = 'Announcement is being delivered to every active user’s notifications.';
    }

    public function render()
    {
        return view('livewire.admin.announcements', [
            'announcements' => Announcement::with('author')->latest()->paginate(10),
            'icons' => self::ICONS,
        ]);
    }
}
