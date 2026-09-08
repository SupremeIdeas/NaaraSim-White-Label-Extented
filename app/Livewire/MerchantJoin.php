<?php

namespace App\Livewire;

use App\Models\Merchant;
use App\Support\MerchantBranding;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public merchant invite landing (ROADMAP §Layer 3.3) at /merchant/{slug}/join.
 * Shows the merchant's storefront big + "Powered by NaaraSim", captures the
 * invite into the session, and sends the visitor to register — where the new
 * account is permanently linked to this merchant. Only ACTIVE merchants resolve;
 * anything else 404s (no dead storefronts).
 */
#[Layout('components.layouts.marketing')]
class MerchantJoin extends Component
{
    public Merchant $merchant;

    public function mount(string $slug): void
    {
        $merchant = MerchantBranding::captureInvite($slug);
        abort_if($merchant === null, 404);

        $this->merchant = $merchant;
    }

    public function render()
    {
        return view('livewire.merchant-join');
    }
}
