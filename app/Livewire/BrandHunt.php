<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerVideo;
use App\Models\SocialFollowHandle;
use App\Services\Social\SocialFollowService;
use App\Services\Social\VideoWatchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Brand Partner Hunt (BUILD-6 §C.4) — the platform's own "Follow us" handles plus
 * a scroll-through of brand partners, each a one-time follow-to-earn claim. The
 * reward is a surprise (never shown before the follow) and the grant is
 * server-side + one-time (SocialFollowService); a claimed button is re-checked
 * server-side on every render, never trusted from the client.
 */
#[Layout('components.layouts.customer')]
class BrandHunt extends Component
{
    public ?string $flash = null;

    public function booted(): void
    {
        // Batch 8: Brand Hunt / Brand Directory is a tier-locked feature — a
        // basic white-label fork has it hidden (404) until it pays up. Inert on
        // the master (never locked there).
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT), 404);
    }

    /** Claim a platform handle follow (self-confirmed tap → server grant). */
    public function followHandle(int $id, SocialFollowService $svc): void
    {
        $handle = SocialFollowHandle::active()->find($id);
        if (! $handle) {
            return;
        }
        $this->grant($svc->claim(Auth::user()->fresh(), $handle), $handle->handle_label);
    }

    /** Claim a brand-partner handle follow. */
    public function followBrandHandle(int $id, SocialFollowService $svc): void
    {
        $handle = BrandPartnerHandle::active()->find($id);
        if (! $handle) {
            return;
        }
        $this->grant($svc->claim(Auth::user()->fresh(), $handle), $handle->handle_label);
    }

    private function grant(array $result, string $label): void
    {
        if (! empty($result['capped'])) {
            $this->flash = \App\Support\DailyCreditCap::MESSAGE;
            $this->dispatch('nx-toast', type: 'info', message: $this->flash);

            return;
        }
        if ($result['already']) {
            $this->flash = 'You already claimed this one.';

            return;
        }
        if ($result['earned'] > 0) {
            $this->flash = "+{$result['earned']} NaaraCredits for following {$label}!";
            $this->dispatch('nx-toast', variant: 'hero', type: 'success',
                title: 'Reward unlocked', message: "+{$result['earned']} NaaraCredits added to your balance.");
            $this->dispatch('reward-claimed');
        } else {
            $this->flash = "Thanks for following {$label}!";
        }
    }

        /** Filter to a single category (null = all). */
    public ?string $category = null;

    public function setCategory(?string $category): void
    {
        $this->category = $category ?: null;
    }

    /** Begin a video view session — returns the signed heartbeat token to JS. */
    public function videoStart(int $videoId): ?array
    {
        $video = BrandPartnerVideo::whereHas('brandPartner', fn ($q) => $q->listed())->find($videoId);
        if (! $video) {
            return null;
        }

        return app(VideoWatchService::class)->start(Auth::user()->fresh(), $video);
    }

    /** Process a playback heartbeat; grants the watch credit once ≥60s confirmed. */
    public function videoHeartbeat(string $token, float $currentTime): array
    {
        $r = app(VideoWatchService::class)->heartbeat(Auth::user()->fresh(), $token, $currentTime);
        if (! empty($r['capped'])) {
            $this->flash = \App\Support\DailyCreditCap::MESSAGE;
        } elseif ($r['claimed'] && $r['earned'] > 0) {
            $this->flash = "+{$r['earned']} NaaraCredits for watching!";
            $this->dispatch('nx-toast', variant: 'hero', type: 'success', title: 'Reward unlocked', message: "+{$r['earned']} NaaraCredits added.");
            $this->dispatch('reward-claimed');
        }

        return $r;
    }

    public function render()
    {
        $svc = app(SocialFollowService::class);
        $user = Auth::user();

        $brandQuery = BrandPartner::listed()->directoryOrder()
            ->with(['handles' => fn ($q) => $q->active()->ordered(), 'videos' => fn ($q) => $q->ordered()]);
        if ($this->category) {
            $brandQuery->where('category', $this->category);
        }
        $brands = $brandQuery->get();

        return view('livewire.brand-hunt', [
            'platformHandles' => SocialFollowHandle::active()->ordered()->get(),
            'brands' => $brands,
            // Categories that actually have listed brands, for the filter bar.
            'categories' => BrandPartner::listed()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'claimedHandles' => $svc->claimedHandleIds($user),
            'claimedBrandHandles' => $svc->claimedBrandHandleIds($user),
            'dailyRemaining' => \App\Support\DailyCreditCap::remaining($user),
        ]);
    }
}
