<div class="mx-auto max-w-2xl lg:max-w-5xl"
     x-data="{ view: $persist('{{ $defaultView }}').as('nx_contacts_view'), sheet: false, openAdd() { $wire.cancelEdit(); this.sheet = true; } }"
     @contact-edit.window="sheet = true"
     @contact-saved.window="sheet = false">

    {{-- Header --}}
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Contacts</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $total }} {{ Str::plural('contact', $total) }}@if ($favorites->isNotEmpty()) · {{ $favorites->count() }} favourite{{ $favorites->count() === 1 ? '' : 's' }}@endif
            </p>
        </div>
        <button type="button" @click="openAdd()" aria-label="Add contact"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 transition hover:bg-primary-dark">
            <x-icon name="plus" class="h-5 w-5" />
        </button>
    </div>

    {{-- Search + list/grid toggle --}}
    <div class="mb-5 flex items-center gap-2">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search contacts…"
                   class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-9 text-sm text-slate-900 placeholder-slate-400 focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/30 dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
            <span wire:loading wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2 text-primary"><x-ui.spinner class="h-4 w-4" /></span>
        </div>
        <div class="flex shrink-0 items-center gap-1 rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-white/10 dark:bg-white/5">
            <button type="button" @click="view = 'list'" aria-label="List view" :class="view === 'list' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="flex h-8 w-8 items-center justify-center rounded-full transition"><x-icon name="list" class="h-4 w-4" /></button>
            <button type="button" @click="view = 'grid'" aria-label="Grid view" :class="view === 'grid' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="flex h-8 w-8 items-center justify-center rounded-full transition"><x-icon name="grid" class="h-4 w-4" /></button>
        </div>
    </div>

    {{-- Favourites strip --}}
    @if ($favorites->isNotEmpty() && $search === '')
        <div class="mb-5">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Favourites</p>
            <div class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1">
                @foreach ($favorites as $fav)
                    <div wire:key="fav-{{ $fav->id }}" class="flex w-24 shrink-0 flex-col items-center gap-1.5 rounded-2xl border border-slate-200 nx-glass-tile p-3 text-center dark:border-white/10">
                        @include('partials.contact-avatar', ['contact' => $fav, 'size' => 'h-11 w-11 text-sm'])
                        <span class="w-full truncate text-xs font-medium text-slate-700 dark:text-slate-200">{{ Str::of($fav->name)->before(' ')->whenEmpty(fn () => Str::of($fav->name)) }}</span>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('numbers.dialer', ['to' => $fav->phone_number]) }}" wire:navigate aria-label="Call" class="text-green-500"><x-icon name="phone" class="h-4 w-4" /></a>
                            @if ($ownsLine)
                                <button type="button" wire:click="$dispatch('open-send-message', { to: '{{ $fav->phone_number }}', name: @js($fav->name) })" aria-label="Message" class="text-primary dark:text-teal-300"><x-icon name="message-circle" class="h-4 w-4" /></button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ============ LIST view (default) ============
         §6 desktop two-column: the A–Z letter groups flow into two balanced
         columns on large screens (break-inside-avoid keeps a group whole),
         collapsing to one column on mobile. --}}
    <div x-show="view === 'list'" class="lg:columns-2 lg:gap-5">
        @forelse ($grouped as $letter => $rows)
            <div wire:key="grp-{{ $letter }}" id="sec-{{ $letter }}" class="scroll-mt-4 lg:break-inside-avoid">
                <p class="px-1 pb-1 pt-2 text-xs font-bold text-slate-400">{{ $letter }}</p>
                <div class="mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
                    @foreach ($rows as $c)
                        @include('partials.contact-row', ['c' => $c, 'ownsLine' => $ownsLine, 'last' => $loop->last])
                    @endforeach
                </div>
            </div>
        @empty
            @include('partials.contacts-empty')
        @endforelse

        {{-- A–Z quick index --}}
        @if ($letters->count() > 3)
            <div class="fixed right-1 top-1/2 z-20 flex -translate-y-1/2 flex-col items-center gap-0.5 lg:right-4">
                @foreach ($letters as $l)
                    <a href="#sec-{{ $l }}" class="px-1 text-[10px] font-bold text-slate-400 transition hover:text-primary dark:hover:text-teal-300">{{ $l }}</a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============ GRID view ============ --}}
    <div x-show="view === 'grid'" x-cloak class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        @forelse ($grouped->flatten(1) as $c)
            {{-- §4: the grid card gains the same call + message actions the list
                 row and the favourites already have (was edit-only before). --}}
            <div wire:key="gc-{{ $c->id }}"
                 class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200 nx-glass-tile p-4 text-center transition hover:border-primary/40 hover:shadow-sm dark:border-white/10">
                <button type="button" wire:click="edit({{ $c->id }})" @click="sheet = true"
                        class="flex w-full flex-col items-center gap-2">
                    @include('partials.contact-avatar', ['contact' => $c, 'size' => 'h-14 w-14 text-base'])
                    <span class="w-full truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $c->name }}</span>
                    <span class="w-full truncate text-xs text-slate-400">{{ $c->phone_number }}</span>
                </button>
                <div class="flex items-center gap-1.5">
                    <a href="{{ route('numbers.dialer', ['to' => $c->phone_number]) }}" wire:navigate aria-label="Call {{ $c->name }}"
                       class="flex h-8 w-8 items-center justify-center rounded-full bg-green-50 text-green-600 transition hover:bg-green-100 dark:bg-green-950/40 dark:text-green-300">
                        <x-icon name="phone" class="h-4 w-4" />
                    </a>
                    @if ($ownsLine)
                        <button type="button" wire:click="$dispatch('open-send-message', { to: '{{ $c->phone_number }}', name: @js($c->name) })"
                                aria-label="Message {{ $c->name }}"
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary transition hover:bg-primary/20 dark:bg-teal-500/15 dark:text-teal-300">
                            <x-icon name="message-circle" class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            </div>
        @empty
            @include('partials.contacts-empty')
        @endforelse
    </div>

    {{-- Add / Edit + import (bottom sheet) --}}
    @include('partials.contact-sheet')

    {{-- Send-message modal host — catches `open-send-message` from rows / favourites. --}}
    @livewire('send-message')
</div>
