@php
    // Deterministic brand tint when a logo/colour isn't available (graceful).
    $tint = fn ($p) => $p->brand_color ?: '#'.substr(md5($p->brand_key), 0, 6);
@endphp
<div class="mx-auto max-w-5xl" x-data="{ view: localStorage.getItem('nx_gift_view') || 'grid', detail: @entangle('selectedId') }"
     x-effect="localStorage.setItem('nx_gift_view', view)">
    {{-- Store entry preloader (self-hosted Lottie). Only while the store is live. --}}
    @if ($live)
        <div x-data="{ loading: true }" x-init="setTimeout(() => loading = false, 1300)"
             x-show="loading" x-transition:leave.opacity.duration.500ms
             class="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-3 bg-white/98 dark:bg-[#0D1B2A]/98"
             role="status" aria-live="polite">
            <x-lottie name="gift-preloader" label="Loading gift store" class="h-44 w-44" />
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Opening your gift store…</p>
        </div>
    @endif

    {{-- The Naara Gift mark now lives in the header (App\Support\BrandContext) —
         this is the gift surface, so the header already wears it. The hero
         below is the same admin-customisable system as the dashboard home
         hero, its own independent setting namespace (owner request). --}}
    @include('livewire.partials.gift-cards._hero')

    @if (! $live)
        {{-- Coming Soon — the store flips live automatically the moment the API
             keys are saved (no manual editing). --}}
        <div class="mx-auto max-w-lg rounded-3xl border border-slate-200/70 bg-white p-8 text-center shadow-sm dark:border-white/10 dark:bg-slate-900/60">
            <span class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/15 to-accent/15 text-primary dark:text-teal-300">
                <x-icon name="gift" class="h-8 w-8" />
            </span>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Naara Gift is coming soon</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Send gift cards for the brands people love — shopping, airtime, streaming and games — delivered instantly by email or WhatsApp. We're putting the finishing touches on the store. Check back shortly.
            </p>
            <a href="{{ route('catalogue') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                <x-icon name="globe" class="h-4 w-4" /> Explore eSIM plans meanwhile
            </a>
        </div>
    @else

    {{-- Search + country + grid/list toggle --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="relative min-w-[200px] flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search brands…"
                   class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-4 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
        </div>
        @if ($countries->isNotEmpty())
            <select wire:model.live="country" class="rounded-full border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                <option value="">All countries</option>
                @foreach ($countries as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
        @endif
        <div class="flex gap-1 rounded-full bg-slate-100 p-1 dark:bg-white/5">
            <button type="button" @click="view = 'list'" :class="view === 'list' ? 'bg-white text-primary shadow-sm dark:bg-white/15 dark:text-teal-300' : 'text-slate-400'" class="rounded-full p-1.5"><x-icon name="list" class="h-4 w-4" /></button>
            <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'bg-white text-primary shadow-sm dark:bg-white/15 dark:text-teal-300' : 'text-slate-400'" class="rounded-full p-1.5"><x-icon name="grid" class="h-4 w-4" /></button>
        </div>
    </div>

    {{-- Brand catalogue --}}
    @if ($products->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-sm text-slate-400 dark:border-white/10">No gift cards available yet.</div>
    @else
        <div :class="view === 'grid' ? 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4' : 'space-y-2'">
            @foreach ($products as $product)
                <button type="button" wire:click="select({{ $product->id }})" wire:key="gc-{{ $product->id }}"
                        class="group flex overflow-hidden rounded-2xl border border-slate-200 bg-white text-left transition hover:shadow-lg dark:border-white/10 dark:bg-slate-900/60"
                        :class="view === 'grid' ? 'flex-col' : 'flex-row items-center'">
                    <div class="flex shrink-0 items-center justify-center overflow-hidden"
                         :class="view === 'grid' ? 'aspect-[4/3] w-full' : 'h-16 w-16'"
                         style="background: linear-gradient(135deg, {{ $tint($product) }}22, {{ $tint($product) }}55);">
                        @if ($product->logo_url)
                            <img src="{{ $product->logo_url }}" alt="{{ $product->brand_name }}" loading="lazy" class="h-full w-full object-contain p-3">
                        @else
                            <span class="text-lg font-bold text-white" style="text-shadow: 0 1px 2px rgba(0,0,0,.3)">{{ \Illuminate\Support\Str::substr($product->brand_name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 p-3">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $product->brand_name }}</p>
                        <p class="text-xs text-slate-400">{{ $product->country }}@if ($product->category) · {{ $product->category }}@endif</p>
                    </div>
                </button>
            @endforeach
        </div>
        <div class="mt-5">{{ $products->links() }}</div>
    @endif

    {{-- Brand detail sheet — modernized to match the eSIM plan-detail card
         treatment (owner request): bordered rounded-3xl panel, a compact logo
         thumbnail beside the name instead of a full banner, a category pill,
         fact-tile denomination buttons, and a bordered price+CTA bar. --}}
    @if ($selected)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="$wire.close()" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/60" wire:click="close"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-t-3xl border border-slate-200 bg-white shadow-2xl dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)] sm:rounded-3xl">
                <button type="button" wire:click="close" class="absolute right-3 top-3 z-10 rounded-full bg-black/30 p-1.5 text-white hover:bg-black/45"><x-icon name="x" class="h-4 w-4" /></button>

                <div class="max-h-[80vh] overflow-y-auto p-6">
                    {{-- Logo thumbnail beside the name — never a full-bleed banner. --}}
                    <div class="flex items-start gap-4">
                        <span class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl sm:h-24 sm:w-24"
                              style="background: linear-gradient(135deg, {{ $tint($selected) }}22, {{ $tint($selected) }}55);">
                            @if ($selected->logo_url)
                                <img src="{{ $selected->logo_url }}" alt="{{ $selected->brand_name }}" class="h-full w-full object-contain p-3">
                            @else
                                <span class="text-2xl font-bold text-slate-700 dark:text-white">{{ \Illuminate\Support\Str::substr($selected->brand_name, 0, 1) }}</span>
                            @endif
                        </span>
                        <div class="min-w-0 pt-1">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
                                <x-icon name="gift" class="h-3.5 w-3.5" /> {{ $selected->category ?: 'Gift Card' }}
                            </span>
                            <h2 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">{{ $selected->brand_name }}</h2>
                            <p class="text-xs text-slate-400">{{ $selected->country }} · {{ $selected->currency }}</p>
                        </div>
                    </div>

                    {{-- Denomination selector, as fact tiles matching the eSIM spec grid. --}}
                    <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Choose an amount</p>
                    @if (($denominations['type'] ?? '') === 'RANGE')
                        <input type="number" wire:model="amount" min="{{ $denominations['min'] }}" max="{{ $denominations['max'] }}"
                               placeholder="{{ $denominations['min'] }} – {{ $denominations['max'] }} {{ $selected->currency }}"
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-2.5 text-sm dark:border-[#243352] dark:bg-[var(--brand-card-inner-dark)] dark:text-slate-100">
                        <p class="mt-1 text-xs text-slate-400">You pay retail; the exact charge is shown at checkout.</p>
                    @else
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (($denominations['options'] ?? []) as $opt)
                                <button type="button" wire:click="$set('amount', {{ $opt['face'] }})"
                                        @class([
                                            'rounded-2xl border p-3 text-center transition',
                                            'border-primary bg-primary/5 dark:bg-primary/10' => (float) $amount === $opt['face'],
                                            'border-slate-100 bg-slate-50/70 hover:border-slate-200 dark:border-[#243352] dark:bg-[var(--brand-card-inner-dark)] dark:hover:border-[#2D4060]' => (float) $amount !== $opt['face'],
                                        ])>
                                    <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ $selected->currency }} {{ number_format($opt['face'], 0) }}</span>
                                    <span class="block text-[11px] text-slate-400">pay ${{ number_format($opt['retail'], 2) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Dynamic required fields --}}
                    @if (! empty($selected->required_fields))
                        <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recipient details</p>
                        <div class="space-y-2">
                            @foreach ($selected->required_fields as $field)
                                @php $k = $field['key'] ?? 'field'; @endphp
                                <input type="{{ $field['type'] ?? 'text' }}" wire:model="fields.{{ $k }}"
                                       placeholder="{{ $field['label'] ?? ucfirst($k) }}"
                                       class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-2.5 text-sm dark:border-[#243352] dark:bg-[var(--brand-card-inner-dark)] dark:text-slate-100">
                            @endforeach
                        </div>
                    @endif

                    {{-- Redemption note --}}
                    @if ($selected->redeem_instruction)
                        <details class="mt-4 rounded-2xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-600 dark:bg-[var(--brand-card-inner-dark)] dark:text-slate-300">
                            <summary class="cursor-pointer font-semibold">How to redeem</summary>
                            <p class="mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($selected->redeem_instruction), 400) }}</p>
                        </details>
                    @endif

                    {{-- Sticky-feel price + CTA bar, matching the eSIM detail screen. --}}
                    @php
                        $selectedRetail = null;
                        if (($denominations['type'] ?? '') !== 'RANGE' && $amount) {
                            $selectedOpt = collect($denominations['options'] ?? [])->firstWhere('face', (float) $amount);
                            $selectedRetail = $selectedOpt['retail'] ?? null;
                        }
                    @endphp
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-5 dark:border-[var(--brand-card-border-dark)]">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400">You pay</p>
                            <div class="text-2xl font-extrabold text-slate-900 dark:text-slate-100">
                                {{ $selectedRetail !== null ? '$'.number_format((float) $selectedRetail, 2) : '—' }}
                            </div>
                            @if (($denominations['type'] ?? '') === 'RANGE' && $amount)
                                <p class="text-xs text-slate-400">Exact charge shown at checkout</p>
                            @endif
                        </div>
                        <button type="button" wire:click="buy" wire:loading.attr="disabled" wire:target="buy" @disabled(! $amount)
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-gradient-to-br from-primary via-primary-dark to-navy px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/25 transition hover:-translate-y-0.5 hover:shadow-xl disabled:pointer-events-none disabled:opacity-50 sm:flex-none">
                            <span wire:loading.remove wire:target="buy" class="inline-flex items-center gap-1.5"><x-icon name="gift" class="h-4 w-4" /> Buy gift card</span>
                            <span wire:loading wire:target="buy" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Processing…</span>
                        </button>
                    </div>
                    <p class="mt-2 text-center text-[11px] text-slate-400">Gift cards are final — no refunds once delivered.</p>
                </div>
            </div>
        </div>
    @endif
    @endif {{-- /$live --}}
</div>
