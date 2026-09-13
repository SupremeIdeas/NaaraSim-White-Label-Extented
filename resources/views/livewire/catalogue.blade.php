<div>
    {{-- Per-request USD + local-currency formatter (live FX, never cost). --}}
    @php($fmt = fn ($usd) => app(\App\Services\Pricing\CurrencyService::class)
        ->localPrice((float) $usd, \App\Support\LocaleCurrency::resolve(auth()->user())))

    {{-- One compatibility modal + the ONE shared country picker (S31). Always
         mounted so their events work from any screen. --}}
    <livewire:esim-compatibility />
    <livewire:country-picker />

    {{-- ============================ PLAN DETAIL (§3.4) ============================
         A dedicated, focused screen — no front chrome. --}}
    @if ($screen === 'detail')
        <button type="button" wire:click="back"
                class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back
        </button>

        <div class="overflow-hidden rounded-3xl border border-slate-200 nx-glass-tile shadow-sm dark:border-[var(--brand-card-border-dark)]">
            <div class="p-6">
                {{-- The plan's coverage art sits beside the title as a compact
                     thumbnail — never a full-bleed cover over the page. --}}
                <div class="flex items-start gap-4">
                    <span class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 sm:h-24 sm:w-24 dark:from-primary/20 dark:to-transparent">
                        @if ($banner)
                            <img src="{{ $banner }}" alt="{{ $plan->name }}" class="h-full w-full object-cover">
                        @else
                            <x-icon name="globe" class="h-9 w-9 text-primary/60" gradient />
                        @endif
                    </span>
                    <div class="min-w-0 pt-1">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
                            <x-icon name="globe" class="h-3.5 w-3.5" /> {{ $plan->type ?? 'Data' }}
                        </span>
                        <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $plan->name }}</h2>
                    </div>
                </div>

                {{-- Key facts as a clean spec grid (honest, synced data only). --}}
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($facts as $fact)
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-3 dark:border-[#243352] dark:bg-[var(--brand-card-inner-dark)]">
                            <x-icon name="{{ $fact['icon'] }}" class="h-5 w-5 text-primary dark:text-teal-300" />
                            <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $fact['label'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Fair-usage disclosure (Prompt 10): structurally required for every
                     unlimited plan, never optional — the accessor itself returns null
                     for a data-capped plan, so this only ever renders when it applies. --}}
                @if ($plan->display_fair_usage_note)
                    <p class="mt-3 flex items-start gap-2 rounded-2xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                        <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ $plan->display_fair_usage_note }}</span>
                    </p>
                @endif

                {{-- AI tooltip (§5) as the "what am I buying" copy, when present. --}}
                @if ($plan->display_tooltip)
                    <p class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:bg-[var(--brand-card-inner-dark)] dark:text-slate-300">
                        {{ $plan->display_tooltip }}
                    </p>
                @endif

                {{-- Inline compatibility prompt at the point of plan selection (Prompt 10
                     §3): a lightweight nudge before checkout, reusing the same modal +
                     device catalogue as the marketing hero's "Check compatibility". This
                     is advisory only — it never gates this screen; the authoritative,
                     warning-not-block confirmation still runs at Checkout. --}}
                <button type="button" @click="$dispatch('open-compatibility')"
                        class="mt-5 flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-left text-sm text-slate-600 transition hover:border-primary/40 hover:bg-primary/5 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-inner-dark)] dark:text-slate-300">
                    <x-icon name="signal" class="h-4 w-4 shrink-0 text-primary" />
                    <span class="min-w-0 flex-1">Is your device eSIM-ready? <span class="font-semibold text-primary">Check compatibility</span></span>
                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400" />
                </button>

                {{-- Sticky-feel price + CTA bar. --}}
                @php($price = $fmt((float) $plan->final_retail_usd))
                <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-5 dark:border-[var(--brand-card-border-dark)]">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">You pay</p>
                        <div class="text-3xl font-extrabold text-slate-900 dark:text-slate-100">{{ $price['usd'] }}</div>
                        @if ($price['local'])
                            <div class="text-xs text-slate-500 dark:text-slate-400">≈ {{ $price['local'] }}</div>
                        @endif
                    </div>
                    <a href="{{ route('checkout', $plan) }}" wire:navigate
                       class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-gradient-to-br from-primary via-primary-dark to-navy px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/25 transition hover:-translate-y-0.5 hover:shadow-xl sm:flex-none">
                        Buy this plan <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </div>

    {{-- ==================== DEDICATED COUNTRY / REGION PAGE ==================== --}}
    @elseif ($screen === 'country' || $screen === 'region')
        <button type="button" wire:click="back"
                class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back
        </button>

        @if ($screen === 'region')
            {{-- Region header: the admin map photo reads well large, so it
                 stays a wide banner with the name overlaid. --}}
            <div class="mb-5 overflow-hidden rounded-3xl border border-slate-200 nx-glass-tile shadow-sm dark:border-[var(--brand-card-border-dark)]">
                @if ($banner)
                    <div class="relative h-36 w-full overflow-hidden sm:h-44">
                        <img src="{{ $banner }}" alt="{{ $selName }}" class="h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/10 to-transparent"></div>
                        <div class="absolute bottom-4 left-5 text-white">
                            <h1 class="text-xl font-bold drop-shadow">{{ $selName }}</h1>
                            <p class="text-xs text-white/85">Regional plans</p>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3 p-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 dark:bg-[var(--brand-card-inner-dark)]">
                            <x-icon name="globe" class="h-7 w-7 text-primary" gradient />
                        </span>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ $selName }}</h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Multi-country plans in this region</p>
                        </div>
                    </div>
                @endif
            </div>
        @else
            {{-- Country header: the country's cutout art sits beside the name
                 as a compact thumbnail — it never covers the page as a banner. --}}
            <div class="mb-5 flex items-center gap-4 rounded-3xl border border-slate-200 nx-glass-tile p-4 shadow-sm dark:border-[var(--brand-card-border-dark)]">
                <span class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 sm:h-24 sm:w-24 dark:bg-[var(--brand-card-inner-dark)]">
                    @if ($banner)
                        <img src="{{ $banner }}" alt="{{ $selName }}" class="h-full w-full object-cover">
                    @else
                        <x-country-flag :country="$selCode" class="h-9 w-12 rounded shadow-sm" />
                    @endif
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <x-country-flag :country="$selCode" class="h-4 w-6 shrink-0 rounded shadow-sm" />
                        <h1 class="truncate text-xl font-bold text-slate-900 dark:text-slate-100">{{ $selName }}</h1>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $tab === 'full' ? 'Calls + data plans, valid in this country' : 'Data plans, valid in this country' }}
                    </p>
                </div>
            </div>
        @endif

        <div class="mb-2 flex items-baseline justify-between">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Choose a package</p>
            <p class="text-xs text-slate-400">{{ $plans->total() }} {{ \Illuminate\Support\Str::plural('plan', $plans->total()) }}</p>
        </div>
        @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])

    {{-- =============================== FRONT =============================== --}}
    @else
        {{-- Hero + control block, positioned per the eSIM theme variant
             (variant-a: hero then controls — the reference order; variant-b:
             controls then hero). HEADER/HERO REFLOW ONLY per theme. --}}
        @php($esimVariant = \App\Support\ThemePreset::layoutVariant('esim'))
        @if ($esimVariant === 'variant-b')
            @include('livewire.partials.esim.head')
            @include('partials.esim-hero')
        @else
            @include('partials.esim-hero')
            @include('livewire.partials.esim.head')
        @endif

        {{-- Naara Connect (Full) coming-soon state when the line has no plans. --}}
        @if ($tab === 'full' && $fullCount === 0 && $screen === 'grid')
            <div class="mb-6 flex flex-col items-center gap-2 rounded-3xl border border-dashed border-slate-300 py-14 text-center dark:border-[var(--brand-card-border-dark)]">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="signal" class="h-6 w-6" gradient /></span>
                <p class="font-semibold text-slate-800 dark:text-slate-100">Naara Connect is coming soon</p>
                <p class="max-w-sm text-sm text-slate-500 dark:text-slate-400">Calls + data on one eSIM — with a number, minutes and SMS. We're finishing the last checks with our voice provider.</p>
            </div>
        @endif

        {{-- -------------------------------- SEARCH -------------------------------- --}}
        @if ($screen === 'search')
            @if (count($countryHits) || count($regionHits))
                <div class="mb-6 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    @foreach ($countryHits as $t)
                        @include('livewire.catalogue._country-row', ['t' => $t, 'fmt' => $fmt])
                    @endforeach
                    @foreach ($regionHits as $t)
                        @include('livewire.catalogue._region-row', ['t' => $t, 'fmt' => $fmt])
                    @endforeach
                </div>
            @endif
            @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])

        {{-- --------------------------------- GRID -------------------------------- --}}
        @else
            @if ($view === 'popular')
                {{-- Trending: the popular-destinations photo cards, then the
                     featured plan list. Both drill into the same country page. --}}
                @include('livewire.catalogue._popular-destinations', ['popularDestinations' => $popularDestinations, 'fmt' => $fmt])
                @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])

            @elseif ($view === 'local')
                @if (count($grid['local']))
                    <div class="mb-2 flex items-baseline justify-between">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Choose a country</p>
                        <p class="text-xs text-slate-400">{{ count($grid['local']) }} {{ \Illuminate\Support\Str::plural('country', count($grid['local'])) }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        @foreach ($grid['local'] as $t)
                            @include('livewire.catalogue._country-row', ['t' => $t, 'fmt' => $fmt])
                        @endforeach
                    </div>
                @else
                    <x-esim.empty-nav />
                @endif

            @elseif ($view === 'regional')
                @if (count($grid['regions']))
                    <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Pick a region</p>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        @foreach ($grid['regions'] as $t)
                            @include('livewire.catalogue._region-row', ['t' => $t, 'fmt' => $fmt])
                        @endforeach
                    </div>
                @else
                    <x-esim.empty-nav />
                @endif

            @else {{-- global --}}
                @if ($globalCount > 0)
                    <div class="relative mb-5 overflow-hidden rounded-3xl border border-primary/20 bg-gradient-to-br from-primary/10 to-primary/5 p-5 dark:border-primary/30 dark:from-primary/20 dark:to-transparent">
                        @if ($globalBanner)
                            <img src="{{ $globalBanner }}" alt="Global" class="pointer-events-none absolute -right-6 -top-2 h-32 w-40 object-contain opacity-70">
                        @else
                            <x-icon name="globe" class="pointer-events-none absolute -right-2 top-2 h-28 w-28 text-primary/25" gradient />
                        @endif
                        <p class="text-[11px] font-bold uppercase tracking-wider text-primary dark:text-teal-300">One plan, everywhere</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Global eSIM</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Stay connected across 190+ countries on a single eSIM.</p>
                    </div>
                    @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])
                @else
                    <x-esim.empty-nav />
                @endif
            @endif
        @endif

        {{-- Trust/feature strip — foot of the front, all themes. --}}
        @include('livewire.catalogue._feature-strip')
    @endif
</div>
