<div class="mx-auto max-w-3xl">
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

    {{-- BUILD-3 §6.10: title + description sit BESIDE the Lottie, not stacked
         over it. The text column flexes while the animation stays a fixed,
         smaller size on mobile so the copy is never crushed. --}}
    <div class="mb-4 flex items-center gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Rewards</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Earn <span class="font-semibold text-primary dark:text-teal-300">NaaraCredits</span> and spend them like cash on eSIMs and numbers. {{ $perUsd }} credits = $1.</p>
        </div>
        {{-- Hero illustration (self-hosted Lottie, reduced-motion aware). --}}
        <x-lottie name="reward" label="Rewards" class="h-24 w-24 shrink-0 sm:h-40 sm:w-40" />
    </div>

    @unless ($enabled)
        <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-400 dark:border-[var(--brand-card-border-dark)] dark:text-slate-500">
            The rewards programme is currently paused. Check back soon.
        </div>
    @else
        {{-- Balance card --}}
        <div class="nx-aurora mb-6">
            <span class="nx-aurora__glow nx-aurora__glow--1" aria-hidden="true"></span>
            <span class="nx-aurora__glow nx-aurora__glow--2" aria-hidden="true"></span>
            <div class="relative flex items-end justify-between gap-4">
                <div>
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-teal-100/90">
                        <x-naara-coin class="h-4 w-4" /> NaaraCredits balance
                    </p>
                    <p class="mt-3 flex items-center gap-2 font-display text-4xl font-bold tracking-tight text-white">
                        <x-naara-coin class="h-7 w-7" /> {{ number_format($balance, 0) }}
                    </p>
                    <p class="mt-1 text-sm text-teal-100/80">worth ${{ number_format($usdValue, 2) }} at checkout</p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <a href="{{ route('catalogue') }}" class="rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-accent hover:text-navy">Spend credits</a>
                    @if ($canWithdraw)
                        <a href="{{ route('rewards.withdraw') }}" class="rounded-full bg-accent px-4 py-2 text-sm font-semibold text-navy transition hover:bg-white">Withdraw ${{ number_format($withdrawableUsd, 2) }}</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- §5.1: if there's cash to withdraw but the identity gate isn't met,
             explain it inline (self-hides once verified). --}}
        @if ($withdrawableUsd > 0)
            <x-kyc-gate-notice :level="2" action="cash out your credits" class="mt-4" />
        @endif

        @if ($flash)
            <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
                <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $flash }}
            </div>
        @endif

        {{-- Earn methods --}}
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ways to earn</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Daily check-in --}}
            <div class="nx-card flex flex-col">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="badge-check" class="h-5 w-5" /></span>
                <h3 class="mt-4 font-bold text-slate-900 dark:text-white">Daily check-in</h3>
                <p class="mt-1.5 flex-1 text-sm text-slate-600 dark:text-slate-300">Come back each day and collect {{ $checkinDaily }} credits — free, no strings.</p>
                <button type="button" wire:click="checkIn" wire:loading.attr="disabled" wire:target="checkIn" @disabled(! $canCheckIn)
                        class="nx-btn nx-btn--primary mt-4 justify-center disabled:opacity-50">
                    <span wire:loading.remove wire:target="checkIn">{{ $canCheckIn ? 'Check in — +'.$checkinDaily : 'Come back later' }}</span>
                    <span wire:loading wire:target="checkIn" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Checking in…</span>
                </button>
            </div>

            {{-- Referrals --}}
            <div class="nx-card flex flex-col">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-accent/15 text-accent-dark dark:text-accent"><x-icon name="gift" class="h-5 w-5" /></span>
                <h3 class="mt-4 font-bold text-slate-900 dark:text-white">Invite friends</h3>
                <p class="mt-1.5 flex-1 text-sm text-slate-600 dark:text-slate-300">Share your link — when a friend joins and buys, you earn a reward.</p>
                <a href="{{ route('referrals') }}" class="nx-btn nx-btn--ghost mt-4 justify-center">Get my link</a>
            </div>

            {{-- Brand Partner Hunt (BUILD-6 §C) — a distinct, teasing CTA (no handles/amounts shown here). --}}
            <a href="{{ route('rewards.hunt') }}" wire:navigate
               class="group relative flex flex-col overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-[#085555] p-6 text-white shadow-lg sm:col-span-2">
                <span class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10 blur-2xl"></span>
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15"><x-icon name="star" class="h-5 w-5" /></span>
                <h3 class="mt-4 text-lg font-bold">Want more NaaraCredit? Begin the hunt</h3>
                <p class="mt-1.5 max-w-xl text-sm text-white/80">Follow featured brands and NaaraSim across social — each follow unlocks a surprise credit reward. One tap to start.</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold">Start the hunt <x-icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" /></span>
            </a>

            {{-- Watch an ad — opt-in, only when a compliant provider is configured --}}
            <div class="nx-card flex flex-col sm:col-span-2">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="signal" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-slate-900 dark:text-white">Watch &amp; earn</h3>
                        <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-300">
                            Prefer to earn with your time? Watch a short sponsored video or complete an offer and get credits. This is entirely optional — ads only ever appear here, when you choose to earn. They never interrupt you anywhere else on {{ \App\Support\BrandSettings::name() }}.
                        </p>
                        @if ($adsActive)
                            <button type="button"
                                    x-data
                                    @click="window.open(@js($offerwallUrl), 'naara_offers', 'width=420,height=680,noopener')"
                                    class="nx-btn nx-btn--gold mt-4 justify-center">
                                <x-icon name="signal" class="h-4 w-4" /> Open the rewards wall
                            </button>
                            <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">Your reward is confirmed automatically once the video/offer is verified as completed — it may take a moment to land in your balance.</p>
                        @else
                            <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                                <x-icon name="signal" class="h-3.5 w-3.5" /> Coming soon — watch this space.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Ledger --}}
        <h2 class="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Credit history</h2>
        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-[var(--brand-card-border-dark)]">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Activity</th>
                        <th class="px-4 py-2 font-medium">Credits</th>
                        <th class="px-4 py-2 font-medium">Balance</th>
                        <th class="px-4 py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[var(--brand-card-dark)]">
                    @forelse ($ledger as $row)
                        <tr wire:key="cl-{{ $row->id }}" class="text-slate-700 dark:text-slate-200">
                            <td class="px-4 py-2 capitalize">{{ $row->description ?? str_replace('_', ' ', $row->source) }}</td>
                            <td class="px-4 py-2 font-medium {{ $row->type === 'spend' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ $row->type === 'spend' ? '−' : '+' }}{{ number_format((float) $row->amount, 0) }}
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ number_format((float) $row->balance_after, 0) }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ $row->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No credits yet — check in above to start earning.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>
