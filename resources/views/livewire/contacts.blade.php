{{-- Contacts on the skin system (S3 Batch 3): same A-Z list / grid / favourites / swipe-to-delete / add-edit sheet, now on tokens. --}}
<div>
<x-nx.page class="ns-narrow ns-ct"
     x-data="{ view: $persist('{{ $defaultView }}').as('nx_contacts_view'), sheet: false, openAdd() { $wire.cancelEdit(); this.sheet = true; } }"
     @contact-edit.window="sheet = true"
     @contact-saved.window="sheet = false">

    <div class="ns-ct__top">
        <div>
            <h1 class="ns-h1" style="margin-top:6px">Contacts</h1>
            <p class="ns-sub">
                {{ $total }} {{ Str::plural('contact', $total) }}@if ($favorites->isNotEmpty()) · {{ $favorites->count() }} favourite{{ $favorites->count() === 1 ? '' : 's' }}@endif
            </p>
        </div>
        <button type="button" @click="openAdd()" aria-label="Add contact" class="ns-ct__add"><x-nx.icon name="plus" /></button>
    </div>

    {{-- Search + list/grid toggle --}}
    <div class="ns-ct__tools">
        <label class="ns-search ns-ct__search">
            <x-nx.icon name="search" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search contacts…" aria-label="Search contacts">
            <span wire:loading wire:target="search" class="ns-ct__spin"><x-ui.spinner class="h-4 w-4" /></span>
        </label>
        <div class="ns-seg ns-small ns-ct__view" role="group" aria-label="View">
            <button type="button" @click="view = 'list'" aria-label="List view" :class="view === 'list' ? 'is-on' : ''" :aria-pressed="view === 'list'"><x-nx.icon name="list" /></button>
            <button type="button" @click="view = 'grid'" aria-label="Grid view" :class="view === 'grid' ? 'is-on' : ''" :aria-pressed="view === 'grid'"><x-nx.icon name="grid" /></button>
        </div>
    </div>

    {{-- Favourites strip --}}
    @if ($favorites->isNotEmpty() && $search === '')
        <div class="ns-section">
            <span class="ns-lbl">Favourites</span>
            <div class="ns-ct__favs">
                @foreach ($favorites as $fav)
                    <div wire:key="fav-{{ $fav->id }}" class="ns-ct__fav ns-ring">
                        @include('partials.contact-avatar', ['contact' => $fav, 'size' => 'h-11 w-11 text-sm'])
                        <span class="ns-ct__favname">{{ Str::of($fav->name)->before(' ')->whenEmpty(fn () => Str::of($fav->name)) }}</span>
                        <span class="ns-ct__acts">
                            <a href="{{ route('numbers.dialer', ['to' => $fav->phone_number]) }}" wire:navigate aria-label="Call {{ $fav->name }}" class="ns-ct__act ns-ct__act--call"><x-nx.icon name="phone" /></a>
                            @if ($ownsLine)
                                <button type="button" wire:click="$dispatch('open-send-message', { to: '{{ $fav->phone_number }}', name: @js($fav->name) })" aria-label="Message {{ $fav->name }}" class="ns-ct__act ns-ct__act--msg"><x-nx.icon name="message-circle" /></button>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- LIST view: the A-Z groups flow into two balanced columns on large screens. --}}
    <div x-show="view === 'list'" class="ns-ct__list">
        @forelse ($grouped as $letter => $rows)
            <div wire:key="grp-{{ $letter }}" id="sec-{{ $letter }}" class="ns-ct__group">
                <span class="ns-lbl">{{ $letter }}</span>
                <div class="ns-ct__rows ns-ring">
                    @foreach ($rows as $c)
                        @include('partials.contact-row', ['c' => $c, 'ownsLine' => $ownsLine, 'last' => $loop->last])
                    @endforeach
                </div>
            </div>
        @empty
            @include('partials.contacts-empty')
        @endforelse

        @if ($letters->count() > 3)
            <nav class="ns-ct__az" aria-label="Jump to letter">
                @foreach ($letters as $l)<a href="#sec-{{ $l }}">{{ $l }}</a>@endforeach
            </nav>
        @endif
    </div>

    {{-- GRID view --}}
    <div x-show="view === 'grid'" x-cloak class="ns-ct__grid">
        @forelse ($grouped->flatten(1) as $c)
            <div wire:key="gc-{{ $c->id }}" class="ns-ct__card ns-ring">
                <button type="button" wire:click="edit({{ $c->id }})" @click="sheet = true" class="ns-ct__cardbody">
                    @include('partials.contact-avatar', ['contact' => $c, 'size' => 'h-14 w-14 text-base'])
                    <b>{{ $c->name }}</b>
                    <small>{{ $c->phone_number }}</small>
                </button>
                <span class="ns-ct__acts">
                    <a href="{{ route('numbers.dialer', ['to' => $c->phone_number]) }}" wire:navigate aria-label="Call {{ $c->name }}" class="ns-ct__act ns-ct__act--call"><x-nx.icon name="phone" /></a>
                    @if ($ownsLine)
                        <button type="button" wire:click="$dispatch('open-send-message', { to: '{{ $c->phone_number }}', name: @js($c->name) })" aria-label="Message {{ $c->name }}" class="ns-ct__act ns-ct__act--msg"><x-nx.icon name="message-circle" /></button>
                    @endif
                </span>
            </div>
        @empty
            @include('partials.contacts-empty')
        @endforelse
    </div>

    @include('partials.contact-sheet')
</x-nx.page>

{{-- Send-message modal host: catches `open-send-message` from rows and favourites. --}}
@livewire('send-message')
</div>
