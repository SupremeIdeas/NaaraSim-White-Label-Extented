@php
    // Deterministic brand tint when a logo/colour isn't available (graceful).
    $tint = fn ($p) => $p->brand_color ?: '#'.substr(md5($p->brand_key), 0, 6);
@endphp
<div>
<x-nx.page class="ns-gf" x-data="{ view: localStorage.getItem('nx_gift_view') || 'grid' }"
     x-effect="localStorage.setItem('nx_gift_view', view)">
    {{-- Store entry preloader (self-hosted Lottie). Only while the store is live. --}}
    @if ($live)
        <div x-data="{ loading: true }" x-init="setTimeout(() => loading = false, 1300)"
             x-show="loading" x-transition:leave.opacity.duration.500ms class="ns-gf__preload" role="status" aria-live="polite">
            <x-lottie name="gift-preloader" label="Loading gift store" class="h-44 w-44" />
            <p class="ns-sub">Opening your gift store…</p>
        </div>
    @endif

    {{-- The Naara Gift mark lives in the header (App\Support\BrandContext). The hero is the admin-customisable system, its own setting namespace. --}}
    @include('livewire.partials.gift-cards._hero')

    @if (! $live)
        {{-- Coming Soon: the store flips live the moment the API keys are saved. --}}
        <div class="ns-card ns-gf__soon">
            <span class="ns-tile" style="width:64px;height:64px;font-size:32px;margin:0 auto 16px"><x-nx.icon name="gift" /></span>
            <h2 class="ns-h1" style="font-size:22px">Naara Gift is coming soon</h2>
            <p class="ns-sub" style="margin-top:8px">
                Send gift cards for the brands people love (shopping, airtime, streaming and games), delivered instantly by email or WhatsApp. We're putting the finishing touches on the store. Check back shortly.
            </p>
            <a href="{{ route('catalogue') }}" wire:navigate class="ns-cta ns-cta--pill" style="margin:22px auto 0"><x-nx.icon name="globe" /> Explore eSIM plans meanwhile</a>
        </div>
    @else

    {{-- Search + country + grid/list toggle --}}
    <div class="ns-gf__tools">
        <label class="ns-search ns-gf__search">
            <x-nx.icon name="search" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search brands…" aria-label="Search brands">
        </label>
        @if ($countries->isNotEmpty())
            <select wire:model.live="country" class="ns-input ns-gf__country" aria-label="Country">
                <option value="">All countries</option>
                @foreach ($countries as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
        @endif
        <div class="ns-seg ns-small" role="group" aria-label="View">
            <button type="button" @click="view = 'list'" :class="view === 'list' ? 'is-on' : ''" :aria-pressed="view === 'list'" aria-label="List view"><x-nx.icon name="list" /></button>
            <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'is-on' : ''" :aria-pressed="view === 'grid'" aria-label="Grid view"><x-nx.icon name="grid" /></button>
        </div>
    </div>

    {{-- Brand catalogue --}}
    @if ($products->isEmpty())
        <x-nx.empty text="No gift cards available yet." style="margin-top:16px" />
    @else
        <div class="ns-gf__brands" :class="view === 'grid' ? 'is-grid' : 'is-list'">
            @foreach ($products as $product)
                <button type="button" wire:click="select({{ $product->id }})" wire:key="gc-{{ $product->id }}" class="ns-gf__brand ns-ring">
                    <span class="ns-gf__logo" style="--gf-tint: {{ $tint($product) }}">
                        @if ($product->logo_url)
                            <img src="{{ $product->logo_url }}" alt="{{ $product->brand_name }}" loading="lazy">
                        @else
                            <b>{{ \Illuminate\Support\Str::substr($product->brand_name, 0, 1) }}</b>
                        @endif
                    </span>
                    <span class="ns-gf__brandtext">
                        <b>{{ $product->brand_name }}</b>
                        <small>{{ $product->country }}@if ($product->category) · {{ $product->category }}@endif</small>
                    </span>
                </button>
            @endforeach
        </div>
        <div class="ns-gf__pages">{{ $products->links() }}</div>
    @endif

    {{-- Brand detail sheet: logo thumbnail beside the name, category pill, fact-tile amounts, price + CTA bar. --}}
    @if ($selected)
        <div class="ns-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.close()" role="dialog" aria-modal="true" aria-label="{{ $selected->brand_name }}">
            <div class="ns-scrim" wire:click="close"></div>
            <div class="ns-sheet">
                <div class="ns-handle"></div>
                <div class="ns-sheet__head">
                    <span class="ns-gf__logo ns-gf__logo--sm" style="--gf-tint: {{ $tint($selected) }}">
                        @if ($selected->logo_url)
                            <img src="{{ $selected->logo_url }}" alt="">
                        @else
                            <b>{{ \Illuminate\Support\Str::substr($selected->brand_name, 0, 1) }}</b>
                        @endif
                    </span>
                    <div class="ns-text"><b>{{ $selected->brand_name }}</b><small>{{ $selected->country }} · {{ $selected->currency }}</small></div>
                    <button type="button" class="ns-sheet__close" wire:click="close" aria-label="Close"><x-nx.icon name="x" /></button>
                </div>
                <div class="ns-sheet__body">
                    <div style="margin-bottom:4px"><x-nx.pill variant="best"><x-nx.icon name="gift" /> {{ $selected->category ?: 'Gift Card' }}</x-nx.pill></div>

                    <span class="ns-lbl" style="margin-top:18px">Choose an amount</span>
                    @if (($denominations['type'] ?? '') === 'RANGE')
                        <input type="number" wire:model="amount" min="{{ $denominations['min'] }}" max="{{ $denominations['max'] }}"
                               placeholder="{{ $denominations['min'] }} – {{ $denominations['max'] }} {{ $selected->currency }}" class="ns-input" aria-label="Amount">
                        <p class="ns-small" style="margin-top:6px">You pay retail; the exact charge is shown at checkout.</p>
                    @else
                        <div class="ns-gf__amts">
                            @foreach (($denominations['options'] ?? []) as $opt)
                                <button type="button" wire:click="$set('amount', {{ $opt['face'] }})" class="ns-gf__amt {{ (float) $amount === $opt['face'] ? 'is-on' : '' }}" aria-pressed="{{ (float) $amount === $opt['face'] ? 'true' : 'false' }}">
                                    <b>{{ $selected->currency }} {{ number_format($opt['face'], 0) }}</b>
                                    <small>pay ${{ number_format($opt['retail'], 2) }}</small>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Dynamic required fields --}}
                    @if (! empty($selected->required_fields))
                        <span class="ns-lbl" style="margin-top:18px">Recipient details</span>
                        <div class="ns-gf__fields">
                            @foreach ($selected->required_fields as $field)
                                @php $k = $field['key'] ?? 'field'; @endphp
                                <input type="{{ $field['type'] ?? 'text' }}" wire:model="fields.{{ $k }}" placeholder="{{ $field['label'] ?? ucfirst($k) }}" aria-label="{{ $field['label'] ?? ucfirst($k) }}" class="ns-input">
                            @endforeach
                        </div>
                    @endif

                    @if ($selected->redeem_instruction)
                        <details class="ns-gf__redeem">
                            <summary>How to redeem</summary>
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags($selected->redeem_instruction), 400) }}</p>
                        </details>
                    @endif
                </div>

                @php
                    $selectedRetail = null;
                    if (($denominations['type'] ?? '') !== 'RANGE' && $amount) {
                        $selectedOpt = collect($denominations['options'] ?? [])->firstWhere('face', (float) $amount);
                        $selectedRetail = $selectedOpt['retail'] ?? null;
                    }
                @endphp
                <div class="ns-sheet__foot">
                    <x-nx.price-bar :pay-label="__('numbers.you_pay')" :total="$selectedRetail !== null ? '$'.number_format((float) $selectedRetail, 2) : '—'" :local="(($denominations['type'] ?? '') === 'RANGE' && $amount) ? 'Exact charge shown at checkout' : null">
                        <button type="button" wire:click="buy" wire:loading.attr="disabled" wire:target="buy" @disabled(! $amount) class="ns-cta">
                            <span wire:loading.remove wire:target="buy" class="ns-cta__label"><x-nx.icon name="gift" /> Buy gift card</span>
                            <span wire:loading wire:target="buy" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Processing…</span>
                        </button>
                        <p class="ns-small" style="text-align:center;margin:0">Gift cards are final. No refunds once delivered.</p>
                    </x-nx.price-bar>
                </div>
            </div>
        </div>
    @endif
    @endif {{-- /$live --}}
</x-nx.page>
</div>
