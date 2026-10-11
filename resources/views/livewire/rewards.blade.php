{{-- Rewards on the skin system (S3 Batch 3). Credits carry the NaaraCredits coin; every action (check-in, hunt, rewards wall, withdraw) and every
     server rule is unchanged. --}}
<x-nx.page class="ns-narrow">
    {{-- One-shot celebratory confetti, fired by the `reward-claimed` event on a
         successful check-in. The node stays mounted (so lottie.js hydrates it);
         we just restart + reveal it briefly. Respects reduced-motion. --}}
    <div x-data="{
             show: false,
             fire() {
                 if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                 const a = $refs.confetti && $refs.confetti.__lottie;
                 if (!a) return;
                 this.show = true; a.goToAndPlay(0, true);
                 clearTimeout(this._t); this._t = setTimeout(() => (this.show = false), 2800);
             }
         }"
         @reward-claimed.window="fire()"
         x-show="show" x-cloak x-transition.opacity
         class="pointer-events-none fixed inset-0 z-[80] flex items-start justify-center" style="display:none;">
        <x-lottie name="rewards-confetti" :loop="false" :autoplay="false" x-ref="confetti" class="h-full w-full max-w-2xl" />
    </div>

    {{-- BUILD-3 §6.10: title + description sit BESIDE the Lottie, not stacked over it. The text column flexes while the animation stays a fixed,
         smaller size on mobile so the copy is never crushed. --}}
    <div style="display:flex;align-items:center;gap:14px">
        <div style="min-width:0;flex:1">
            <h1 class="ns-h1" style="margin-top:6px">Rewards</h1>
            <p class="ns-sub">Earn <b  class="ns-linkink" style="font-weight:600">NaaraCredits</b> and spend them like cash on eSIMs and numbers. {{ $perUsd }} credits = $1.</p>
        </div>
        {{-- Hero illustration (self-hosted Lottie, reduced-motion aware). --}}
        <x-lottie name="reward" label="Rewards" class="h-24 w-24 shrink-0 sm:h-40 sm:w-40" />
    </div>

    @unless ($enabled)
        <x-nx.empty text="The rewards programme is currently paused. Check back soon." style="margin-top:16px" />
    @else
        {{-- Balance card: credits lead (with the coin), the worth in dollars beneath, and the two actions inside the same card. --}}
        <x-nx.balance-hero style="margin-top:16px" class="ns-wallet-hero" label="NaaraCredits balance" :amount="number_format($balance, 0)" :sub="'worth $'.number_format($usdValue, 2).' at checkout'">
            <x-slot:mark><x-naara-coin class="h-4 w-4" /></x-slot:mark>
            <x-slot:amountMark><x-naara-coin class="h-9 w-9" /></x-slot:amountMark>
            <x-slot:footer>
                <div class="ns-balance__actions" style="grid-template-columns:repeat({{ $canWithdraw ? 2 : 1 }}, minmax(0,1fr))">
                    <a href="{{ route('catalogue') }}" wire:navigate class="ns-balance__act"><x-nx.icon name="globe" /> Spend credits</a>
                    @if ($canWithdraw)
                        <a href="{{ route('rewards.withdraw') }}" wire:navigate class="ns-balance__act"><x-nx.icon name="wallet" /> Withdraw ${{ number_format($withdrawableUsd, 2) }}</a>
                    @endif
                </div>
            </x-slot:footer>
        </x-nx.balance-hero>

        {{-- §5.1: if there's cash to withdraw but the identity gate isn't met, explain it inline (self-hides once verified). --}}
        @if ($withdrawableUsd > 0)
            <x-kyc-gate-notice :level="2" action="cash out your credits" class="mt-4" />
        @endif

        @if ($flash)
            <x-nx.note icon="check" role="status">{{ $flash }}</x-nx.note>
        @endif

        {{-- Earn methods --}}
        <div class="ns-lbl" style="margin-top:26px;color:rgb(var(--nx-text));font-weight:600">Ways to earn</div>
        <div class="ns-cards-grid" style="margin-top:10px">
            {{-- Daily check-in --}}
            <div class="ns-card ns-ring ns-earn">
                <span class="ns-card__top"><span class="ns-tile"><x-nx.icon name="check" /></span><x-nx.pill variant="gold">+{{ $checkinDaily }}</x-nx.pill></span>
                <h3>Daily check-in</h3>
                <p>Come back each day and collect {{ $checkinDaily }} credits — free, no strings.</p>
                <button type="button" class="ns-btn ns-btn--solid" style="margin-top:14px;align-self:flex-start" wire:click="checkIn" wire:loading.attr="disabled" wire:target="checkIn" @disabled(! $canCheckIn)>
                    <span wire:loading.remove wire:target="checkIn">{{ $canCheckIn ? 'Check in — +'.$checkinDaily : 'Come back later' }}</span>
                    <span wire:loading.inline-flex wire:target="checkIn" style="gap:8px;align-items:center"><x-ui.spinner class="h-4 w-4" /> Checking in…</span>
                </button>
            </div>

            {{-- Referrals --}}
            <div class="ns-card ns-ring ns-earn">
                <span class="ns-card__top"><span class="ns-tile"><x-nx.icon name="gift" /></span></span>
                <h3>Invite friends</h3>
                <p>Share your link — when a friend joins and buys, you earn a reward.</p>
                <a href="{{ route('referrals') }}" wire:navigate class="ns-btn" style="margin-top:14px;align-self:flex-start">Get my link</a>
            </div>

            {{-- Brand Partner Hunt (BUILD-6 §C) — a distinct, teasing CTA (no handles/amounts shown here). --}}
            <a href="{{ route('rewards.hunt') }}" wire:navigate class="ns-card ns-card--primary ns-earn ns-full">
                <i class="ns-deco" aria-hidden="true"></i>
                <span class="ns-card__top"><span class="ns-tile"><x-nx.icon name="star" /></span></span>
                <h3>Want more NaaraCredit? Begin the hunt</h3>
                <p>Follow featured brands and NaaraSim across social — each follow unlocks a surprise credit reward. One tap to start.</p>
                <span style="display:inline-flex;align-items:center;gap:4px;margin-top:14px;font-weight:600">Start the hunt <x-nx.icon name="chevron-right" /></span>
            </a>

            {{-- Watch an ad — opt-in, only when a compliant provider is configured --}}
            <div class="ns-card ns-ring ns-earn ns-full">
                <span class="ns-card__top"><span class="ns-tile"><x-nx.icon name="bars" /></span></span>
                <h3>Watch &amp; earn</h3>
                <p style="-webkit-line-clamp:unset">Prefer to earn with your time? Watch a short sponsored video or complete an offer and get credits. This is entirely optional — ads only ever appear here, when you choose to earn. They never interrupt you anywhere else on {{ \App\Support\BrandSettings::name() }}.</p>
                @if ($adsActive)
                    <button type="button" x-data class="ns-cta ns-cta--gold" style="margin-top:16px" @click="window.open(@js($offerwallUrl), 'naara_offers', 'width=420,height=680,noopener')"><x-nx.icon name="bars" /> Open the rewards wall</button>
                    <p class="ns-small" style="margin-top:8px">Your reward is confirmed automatically once the video/offer is verified as completed — it may take a moment to land in your balance.</p>
                @else
                    <x-nx.pill variant="out" style="margin-top:14px;align-self:flex-start"><x-nx.icon name="clock" /> Coming soon — watch this space.</x-nx.pill>
                @endif
            </div>
        </div>

        {{-- Ledger --}}
        <div class="ns-lbl" style="margin-top:30px;color:rgb(var(--nx-text));font-weight:600">Credit history</div>
        <div class="ns-card ns-ring" style="padding:6px 14px;margin-top:10px">
            <div style="overflow-x:auto">
                <table class="ns-table">
                    <thead><tr><th style="text-align:start">Activity</th><th style="text-align:end">Credits</th><th style="text-align:end">Balance</th><th style="text-align:end">When</th></tr></thead>
                    <tbody>
                        @forelse ($ledger as $row)
                            <tr wire:key="cl-{{ $row->id }}">
                                <td style="text-transform:capitalize">{{ $row->description ?? str_replace('_', ' ', $row->source) }}</td>
                                <td style="text-align:end;font-weight:600;font-variant-numeric:tabular-nums;color:rgb(var(--nx-{{ $row->type === 'spend' ? 'bad' : 'ok' }}))">{{ $row->type === 'spend' ? '−' : '+' }}{{ number_format((float) $row->amount, 0) }}</td>
                                <td style="text-align:end;font-variant-numeric:tabular-nums">{{ number_format((float) $row->balance_after, 0) }}</td>
                                <td style="text-align:end;white-space:nowrap">{{ $row->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="text-align:center;padding:26px 6px">No credits yet — check in above to start earning.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endunless
</x-nx.page>
