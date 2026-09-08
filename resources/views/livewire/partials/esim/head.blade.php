{{-- eSIM storefront CONTROL BLOCK (reference-matched front, owner request).
     Search + "Browse by country" · the Data Only / Data + Calls line toggle ·
     the Trending / Countries / Regions / Global chip row. Shared by both eSIM
     theme variants — only its position relative to the hero changes per theme
     (variant-a: hero then this; variant-b: this then hero). All Livewire state
     ($tab, $view, $search) lives on the Catalogue component; this partial only
     drives it. --}}

{{-- Search — top of the front, with a Browse-by-country action beside it. --}}
<div class="mb-4 flex items-stretch gap-2">
    <div class="relative flex-1">
        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
            <x-icon name="search" class="h-4 w-4" />
        </span>
        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Where are you travelling to?"
               class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-11 pr-10 text-sm text-slate-900 placeholder-slate-400 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/30 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)] dark:text-slate-100">
        <span wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2 text-primary">
            <x-ui.spinner class="h-4 w-4" />
        </span>
    </div>
    <button type="button" wire:click="browseCountries" aria-label="Browse by country"
            class="flex shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white px-3.5 text-primary shadow-sm transition hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)] dark:text-teal-300 dark:hover:bg-[#243352]">
        <x-icon name="globe" class="h-5 w-5" />
        <span class="sr-only">Browse by country</span>
    </button>
</div>

{{-- Data Only / Data + Calls — the two hard-separated lines (§3.0), styled as
     the reference's full-width segmented toggle. "Naara Connect" (the full line's
     product name) stays in its own coming-soon state below. --}}
<div class="mb-4 grid grid-cols-2 gap-1 rounded-2xl border border-slate-200 bg-slate-100 p-1 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
    <button wire:click="setTab('data')" @class([
        'flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition',
        'bg-primary text-white shadow-sm' => $tab === 'data',
        'text-slate-500 hover:text-slate-700 dark:text-slate-400' => $tab !== 'data',
    ])>
        <x-icon name="sim" class="h-4 w-4" /> Data Only
    </button>
    <button wire:click="setTab('full')" @class([
        'flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition',
        'bg-primary text-white shadow-sm' => $tab === 'full',
        'text-slate-500 hover:text-slate-700 dark:text-slate-400' => $tab !== 'full',
    ])>
        <x-icon name="phone" class="h-4 w-4" /> Data + Calls
    </button>
</div>

{{-- Category chips (reference row): Trending / Countries / Regions / Global.
     Horizontal-scroll on small screens, active state highlighted. These are the
     app's REAL navigation modes (no fabricated continent grouping) — Trending is
     the popular-destinations view, and every country card drills into the same
     tab-scoped country page. --}}
@php($chips = [
    ['popular', 'Trending', 'zap'],
    ['local', 'Countries', 'map-pin'],
    ['regional', 'Regions', 'layers'],
    ['global', 'Global', 'globe'],
])
<div class="-mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" style="scrollbar-width: none;">
    @foreach ($chips as [$key, $label, $icon])
        <button wire:click="setView('{{ $key }}')" @class([
            'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-semibold transition',
            'border-primary bg-primary text-white shadow-sm' => $view === $key,
            'border-slate-200 bg-white text-slate-600 hover:border-primary/40 hover:text-primary dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)] dark:text-slate-300' => $view !== $key,
        ])>
            <x-icon name="{{ $icon }}" class="h-4 w-4" /> {{ $label }}
        </button>
    @endforeach
</div>
