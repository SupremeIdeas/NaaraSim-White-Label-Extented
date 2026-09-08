{{-- iOS contact row: tap the body to edit, swipe left to reveal Delete, with
     favourite / call / message actions. Reduced-motion falls back gracefully. --}}
<div wire:key="row-{{ $c->id }}" class="relative select-none {{ ($last ?? false) ? '' : 'border-b border-slate-100 dark:border-white/5' }}"
     x-data="{ x: 0, sx: 0, dragging: false }"
     @pointerdown="sx = $event.clientX; dragging = true"
     @pointermove="if (dragging) x = Math.max(-88, Math.min(0, $event.clientX - sx))"
     @pointerup="dragging = false; x = x < -44 ? -88 : 0"
     @pointercancel="dragging = false; x = 0"
     @pointerleave="if (dragging) { dragging = false; x = x < -44 ? -88 : 0 }">

    {{-- Delete revealed behind --}}
    <div class="absolute inset-y-0 right-0 flex items-center">
        <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Remove {{ $c->name }} from your contacts?"
                class="flex h-full w-[88px] flex-col items-center justify-center gap-0.5 bg-red-500 text-[11px] font-semibold text-white">
            <x-icon name="trash" class="h-4 w-4" /> Delete
        </button>
    </div>

    {{-- Front row --}}
    <div class="relative flex items-center gap-3 bg-white px-3 py-2.5 transition-transform duration-200 dark:bg-[#0f2030]"
         :style="{ transform: 'translateX(' + x + 'px)' }">
        <button type="button" wire:click="edit({{ $c->id }})" @click="$dispatch('contact-edit')"
                class="flex min-w-0 flex-1 items-center gap-3 text-left">
            @include('partials.contact-avatar', ['contact' => $c, 'size' => 'h-10 w-10 text-sm'])
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $c->name }}</span>
                <span class="block truncate text-xs text-slate-400">{{ $c->phone_number }}</span>
            </span>
        </button>

        <div class="flex shrink-0 items-center gap-1">
            <button type="button" wire:click="toggleFavorite({{ $c->id }})" aria-label="Favourite {{ $c->name }}"
                    class="flex h-9 w-9 items-center justify-center rounded-full transition hover:bg-slate-100 dark:hover:bg-white/10">
                <x-icon name="star" class="h-4 w-4 {{ $c->is_favorite ? 'text-accent-dark fill-accent-dark dark:text-accent dark:fill-accent' : 'text-slate-300 dark:text-slate-600' }}" />
            </button>
            <a href="{{ route('numbers.dialer', ['to' => $c->phone_number]) }}" wire:navigate aria-label="Call {{ $c->name }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-green-50 text-green-600 transition hover:bg-green-100 dark:bg-green-950/40 dark:text-green-300">
                <x-icon name="phone" class="h-4 w-4" />
            </a>
            @if ($ownsLine)
                <button type="button" wire:click="$dispatch('open-send-message', { to: '{{ $c->phone_number }}', name: @js($c->name) })"
                        aria-label="Message {{ $c->name }}"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary transition hover:bg-primary/20 dark:bg-teal-500/15 dark:text-teal-300">
                    <x-icon name="message-circle" class="h-4 w-4" />
                </button>
            @endif
        </div>
    </div>
</div>
