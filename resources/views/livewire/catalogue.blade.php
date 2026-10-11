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
        @php($price = $fmt((float) $plan->final_retail_usd))
        @php($specKinds = ['signal' => 'spec_data', 'refresh' => 'spec_validity', 'phone' => 'spec_includes', 'globe' => 'spec_coverage'])
        <x-nx.page>
            <button type="button" wire:click="back" wire:loading.attr="disabled" wire:target="back" class="ns-back">
                <x-nx.icon name="chevron-left" /> {{ __('esim.back') }}
            </button>

            {{-- The plan's coverage art sits beside the title as a compact thumbnail, never a full-bleed cover over the page. --}}
            <div class="ns-hero">
                <span class="ns-thumb" style="overflow:hidden;display:grid;place-items:center">
                    @if ($banner)
                        <img src="{{ $banner }}" alt="{{ $plan->name }}" style="width:100%;height:100%;object-fit:cover">
                    @else
                        <x-nx.icon name="globe" style="font-size:34px;color:rgb(var(--nx-teal-ink))" />
                    @endif
                </span>
                <div style="min-width:0">
                    <x-nx.pill variant="best">{{ $plan->type ?? __('esim.data_type') }}</x-nx.pill>
                    <h2>{{ $plan->name }}</h2>
                </div>
            </div>

            {{-- Key facts as a spec list (honest, synced data only; nothing assumed). --}}
            <div class="ns-spec">
                @foreach ($facts as $fact)
                    <div>
                        <x-nx.icon :name="$fact['icon']" />
                        <span><small>{{ __('esim.'.($specKinds[$fact['icon']] ?? 'spec_data')) }}</small><b>{{ $fact['label'] }}</b></span>
                    </div>
                @endforeach
            </div>

            {{-- Fair-usage disclosure (Prompt 10): structurally required for every unlimited plan, never optional. The accessor itself
                 returns null for a data-capped plan, so this only ever renders when it applies. --}}
            @if ($plan->display_fair_usage_note)
                <x-nx.note icon="info" variant="warn">{{ $plan->display_fair_usage_note }}</x-nx.note>
            @endif

            {{-- AI tooltip (§5) as the "what am I buying" copy, when present. --}}
            @if ($plan->display_tooltip)
                <x-nx.note icon="info">{{ $plan->display_tooltip }}</x-nx.note>
            @endif

            {{-- Inline compatibility prompt at the point of plan selection (Prompt 10 §3): advisory only, never gates this screen; the
                 authoritative warning-not-block confirmation still runs at Checkout. --}}
            <button type="button" @click="$dispatch('open-compatibility')" class="ns-note ns-note--link ns-ring" style="margin-top:16px">
                <x-nx.icon name="bars" />
                <span><b>{{ __('esim.compat_prompt') }}</b>{{ __('esim.compat_check') }}</span>
                <x-nx.icon name="chevron-right" class="ns-chevron" />
            </button>

            {{-- Price bar + gold CTA: Buy commits wallet funds, so it is the one gold surface on this screen. --}}
            <x-nx.price-bar :pay-label="__('esim.you_pay')" :total="$price['usd']" :local="$price['local'] ? '≈ '.$price['local'] : null" style="margin:18px -16px -28px">
                <a href="{{ route('checkout', $plan) }}" wire:navigate class="ns-cta ns-cta--gold">{{ __('esim.buy_this_plan') }} <x-nx.icon name="chevron-right" /></a>
            </x-nx.price-bar>
        </x-nx.page>

    {{-- ==================== DEDICATED COUNTRY / REGION PAGE ==================== --}}
    @elseif ($screen === 'country' || $screen === 'region')
        <x-nx.page>
            <button type="button" wire:click="back" wire:loading.attr="disabled" wire:target="back" class="ns-back"><x-nx.icon name="chevron-left" /> {{ __('esim.back') }}</button>

            @if ($screen === 'region')
                {{-- Region header: the admin map photo reads well large, so it stays a wide banner with the name overlaid. --}}
                @if ($banner)
                    {{-- nx:allow:start Admin photo banner with a fixed legibility scrim — photographic artwork, not interface colour. --}}
                    <div class="ns-banner-photo">
                        <img src="{{ $banner }}" alt="{{ $selName }}">
                        <div class="ns-banner-photo__text"><h1>{{ $selName }}</h1><p>{{ __('esim.regional_plans') }}</p></div>
                    </div>
                    {{-- nx:allow:end --}}
                @else
                    <div class="ns-hero">
                        <span class="ns-thumb" style="display:grid;place-items:center"><x-nx.icon name="globe" style="font-size:34px;color:rgb(var(--nx-teal-ink))" /></span>
                        <div style="min-width:0"><h1 class="ns-h1" style="font-size:24px;margin:0">{{ $selName }}</h1><p class="ns-sub" style="margin:4px 0 0">{{ __('esim.multi_country_plans') }}</p></div>
                    </div>
                @endif
            @else
                {{-- Country header: the country's cutout art sits beside the name as a compact thumbnail — it never covers the page as a banner. --}}
                <div class="ns-hero">
                    <span class="ns-thumb" style="overflow:hidden;display:grid;place-items:center">
                        @if ($banner)
                            <img src="{{ $banner }}" alt="{{ $selName }}" style="width:100%;height:100%;object-fit:cover">
                        @else
                            <x-country-flag :country="$selCode" class="h-9 w-12 rounded shadow-sm" />
                        @endif
                    </span>
                    <div style="min-width:0">
                        <div style="display:flex;align-items:center;gap:8px">
                            <x-country-flag :country="$selCode" class="h-4 w-6 shrink-0 rounded shadow-sm" />
                            <h1 class="ns-h1" style="font-size:24px;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $selName }}</h1>
                        </div>
                        <p class="ns-sub" style="margin:4px 0 0">{{ $tab === 'full' ? __('esim.full_plans_note') : __('esim.data_plans_note') }}</p>
                    </div>
                </div>
            @endif

            <div class="ns-nrow" style="margin-top:22px"><span class="ns-lbl" style="color:rgb(var(--nx-text));font-weight:600">{{ __('esim.choose_package') }}</span><span class="ns-sub" style="margin:0;font-size:13px">{{ trans_choice('esim.plans_count', $plans->total(), ['count' => $plans->total()]) }}</span></div>
            @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])

            <x-nx.note icon="bars" variant="link" tag="a" :href="route('data-estimator')" wire:navigate>
                <b>{{ __('esim.estimator_title') }}</b>{{ __('esim.estimator_cta') }}
            </x-nx.note>
        </x-nx.page>

    {{-- =============================== FRONT =============================== --}}
    @else
        <x-nx.page>
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
            <x-nx.empty :title="__('esim.full_coming_soon_title')" :text="__('esim.full_coming_soon_body')" style="margin-top:18px" />
        @endif

        {{-- -------------------------------- SEARCH -------------------------------- --}}
        @if ($screen === 'search')
            @if (count($countryHits) || count($regionHits))
                <div class="ns-cards-grid ns-cards-grid--rows">
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
                    <div class="ns-nrow"><span class="ns-lbl">{{ __('esim.choose_country') }}</span><span class="ns-sub" style="margin:0;font-size:13px">{{ trans_choice('esim.countries_count', count($grid['local']), ['count' => count($grid['local'])]) }}</span></div>
                    <div class="ns-cards-grid ns-cards-grid--rows" style="margin-top:8px">
                        @foreach ($grid['local'] as $t)
                            @include('livewire.catalogue._country-row', ['t' => $t, 'fmt' => $fmt])
                        @endforeach
                    </div>
                @else
                    <x-esim.empty-nav />
                @endif

            @elseif ($view === 'regional')
                @if (count($grid['regions']))
                    <div class="ns-nrow"><span class="ns-lbl">{{ __('esim.pick_region') }}</span></div>
                    <div class="ns-cards-grid ns-cards-grid--rows" style="margin-top:8px">
                        @foreach ($grid['regions'] as $t)
                            @include('livewire.catalogue._region-row', ['t' => $t, 'fmt' => $fmt])
                        @endforeach
                    </div>
                @else
                    <x-esim.empty-nav />
                @endif

            @else {{-- global --}}
                @if ($globalCount > 0)
                    <div class="ns-card ns-ring ns-global">
                        @if ($globalBanner)
                            <img src="{{ $globalBanner }}" alt="Global" class="ns-global__art">
                        @else
                            <x-nx.icon name="globe" class="ns-global__art ns-global__art--icon" />
                        @endif
                        <small class="ns-eyebrow">{{ __('esim.global_kicker') }}</small>
                        <h2>{{ __('esim.global_title') }}</h2>
                        <p>{{ __('esim.global_body') }}</p>
                    </div>
                    @include('livewire.catalogue._plan-list', ['plans' => $plans, 'fmt' => $fmt])
                @else
                    <x-esim.empty-nav />
                @endif
            @endif
        @endif

        {{-- Trust/feature strip — foot of the front, all themes. --}}
        @include('livewire.catalogue._feature-strip')
        </x-nx.page>
    @endif
</div>
