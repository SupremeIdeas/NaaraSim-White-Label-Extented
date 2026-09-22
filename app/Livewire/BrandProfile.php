<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Services\Social\BrandFollowService;
use App\Services\Social\SocialFollowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Brand Profile (owner request, 2026-09-22): a dedicated page per brand
 * partner, reached from its card on the Hunt directory — the full gallery,
 * every handle with its own "last post" teaser, and the same follow-to-earn
 * claim as the directory card, just with room to breathe instead of being
 * squeezed into a listing tile.
 */
#[Layout('components.layouts.customer')]
class BrandProfile extends Component
{
    public BrandPartner $brandPartner;

    public ?string $flash = null;

    public function mount(BrandPartner $brandPartner): void
    {
        // Same tier gate as the directory itself.
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT), 404);
        // Only a live, listed brand has a public profile — an unpublished or
        // paused listing 404s here exactly like it's simply absent from the
        // directory, never a "this brand is paused" leak.
        abort_unless(
            BrandPartner::listed()->where('id', $brandPartner->id)->exists(),
            404
        );
        $this->brandPartner = $brandPartner;
    }

    /**
     * "Connect" toggle (owner request, 2026-09-22) — a plain in-platform
     * follow/unfollow, distinct from following an external social handle
     * for a credit reward. No reward here, just a relationship + count.
     */
    public function toggleConnect(BrandFollowService $svc): void
    {
        $result = $svc->toggle(Auth::user(), $this->brandPartner);
        $this->brandPartner->naara_followers_count = $result['count'];
    }

    /** Claim a brand-partner handle follow — identical grant logic to BrandHunt. */
    public function followBrandHandle(int $id, SocialFollowService $svc): void
    {
        $handle = BrandPartnerHandle::active()->find($id);
        if (! $handle || $handle->brand_partner_id !== $this->brandPartner->id) {
            return;
        }
        $result = $svc->claim(Auth::user()->fresh(), $handle);

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
            $this->flash = "+{$result['earned']} NaaraCredits for following {$handle->handle_label}!";
            $this->dispatch('nx-toast', variant: 'hero', type: 'success',
                title: 'Reward unlocked', message: "+{$result['earned']} NaaraCredits added to your balance.");
            $this->dispatch('reward-claimed');
        } else {
            $this->flash = "Thanks for following {$handle->handle_label}!";
        }
    }

    public function render()
    {
        $svc = app(SocialFollowService::class);
        $user = Auth::user();

        $this->brandPartner->load([
            'handles' => fn ($q) => $q->active()->ordered(),
            'images' => fn ($q) => $q->ordered(),
            'videos' => fn ($q) => $q->ordered(),
        ]);

        return view('livewire.brand-profile', [
            'claimedBrandHandles' => $svc->claimedBrandHandleIds($user),
            'dailyRemaining' => \App\Support\DailyCreditCap::remaining($user),
            'isFollowing' => $this->brandPartner->isFollowedBy($user),
        ]);
    }
}
