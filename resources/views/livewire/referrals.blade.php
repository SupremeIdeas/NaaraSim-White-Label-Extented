{{-- Referrals on the skin system (S3 Batch 3): the link lives on a primary card with a gold Copy action (as the wireframe), the refer-and-earn
     animation and every payout/earnings component stay. --}}
<x-nx.page class="ns-narrow" x-data="{ copied: false }">
    {{-- Hero: refer-and-earn illustration beside the copy (self-hosted Lottie, reduced-motion aware). Text flexes; the animation keeps a fixed size. --}}
    <div style="display:flex;align-items:center;gap:14px">
        <div style="min-width:0;flex:1">
            <h1 class="ns-h1" style="margin-top:6px">Refer &amp; earn</h1>
            <p class="ns-sub">Share NaaraSim. When a friend makes their first purchase, you earn store credit — a share of the profit.</p>
        </div>
        <x-lottie name="refer-earn" label="Refer and earn" class="h-24 w-24 shrink-0 sm:h-36 sm:w-36" />
    </div>

    <x-nx.balance-hero style="margin-top:16px" label="Your referral link" icon="users" :amount="''" class="ns-linkcard">
        <x-slot:footer>
            <div class="ns-balance__sub" style="min-height:52px"><input type="text" readonly value="{{ $link }}" x-ref="link" aria-label="Your referral link" class="ns-linkcard__input"></div>
            <div class="ns-balance__actions" style="grid-template-columns:1fr 1fr">
                <button type="button" style="min-height:52px;flex-direction:row" @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 1500)">
                    <x-nx.icon name="copy" /> <span x-text="copied ? 'Copied' : 'Copy link'"></span>
                </button>
                <span class="ns-balance__sub" style="justify-content:center;min-height:52px;gap:8px"><x-nx.icon name="gift" /> Code <b style="font-family:ui-monospace,monospace">{{ $code }}</b></span>
            </div>
        </x-slot:footer>
    </x-nx.balance-hero>

    <div class="ns-lbl" style="margin-top:26px;color:rgb(var(--nx-text));font-weight:600">Your referrals</div>
    @forelse ($referrals as $referral)
        <div wire:key="ref-{{ $referral->id }}" class="ns-row ns-ring" style="height:auto;min-height:52px;padding:10px 14px">
            <x-nx.icon name="users" />
            <span class="ns-row__label">Referral #{{ $referral->referred_id }}</span>
            <x-nx.pill :variant="$referral->rewarded ? 'rec' : null"><x-nx.icon :name="$referral->rewarded ? 'check' : 'clock'" /> {{ $referral->rewarded ? 'Rewarded' : 'Pending' }}</x-nx.pill>
        </div>
    @empty
        <x-nx.empty text="No referrals yet. Share your link to start earning." />
    @endforelse

    {{-- Real, withdrawable referral earnings + payout status (BUILD-22 §1/§4). --}}
    <div class="ns-lbl" style="margin-top:30px;color:rgb(var(--nx-text));font-weight:600">Your cash earnings</div>
    <livewire:payout-dashboard earner-type="referral" />
</x-nx.page>
