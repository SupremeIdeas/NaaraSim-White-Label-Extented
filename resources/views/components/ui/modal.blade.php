@props([
    'name' => null,      // pure-Alpine: opens on window `open-modal` {name} event
    'wire' => null,      // Livewire: entangle the open state to this property
    'title' => null,
    'maxWidth' => 'lg',  // sm|md|lg|xl|2xl
])

@php
    $id = 'modal-'.($name ?? \Illuminate\Support\Str::random(6));
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp

{{--
    The ONE modal engine (blueprint Section 31): focus-trap, ESC + backdrop
    close, body-scroll lock, and full ARIA. Every dialog uses this — no
    per-feature modal markup.
--}}
<div
    x-data="{
        open: @if ($wire) @entangle($wire).live @else false @endif,
        focusables() {
            return [...$refs.panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex=\'-1\'])')]
                .filter(el => el.offsetParent !== null);
        },
        trapTab(e) {
            const f = this.focusables();
            if (! f.length) { e.preventDefault(); return; }
            let i = f.indexOf(document.activeElement);
            i = e.shiftKey ? i - 1 : i + 1;
            if (i < 0) i = f.length - 1;
            if (i >= f.length) i = 0;
            e.preventDefault();
            f[i].focus();
        },
    }"
    @if ($name)
        x-on:open-modal.window="if ($event.detail?.name === '{{ $name }}') open = true"
        x-on:close-modal.window="if (! $event.detail || $event.detail.name === '{{ $name }}') open = false"
    @endif
    x-on:keydown.escape.window="open = false"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''; if (open) $nextTick(() => $refs.panel?.focus())"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[9998] flex items-end justify-center sm:items-center"
    role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
    style="display:none;"
>
    {{-- Backdrop --}}
    <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-navy/50 dark:bg-black/70"></div>

    {{-- Panel --}}
    <div x-ref="panel" tabindex="-1" @keydown.tab="trapTab($event)"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-6 opacity-0 sm:scale-95"
         x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
         class="relative z-10 max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-slate-200 bg-white p-6 shadow-xl outline-none dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)] sm:w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} sm:rounded-2xl">
        <div class="flex items-start justify-between gap-4">
            <h2 id="{{ $id }}-title" class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $title }}</h2>
            <button type="button" @click="open = false" aria-label="Close"
                    class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#243352] dark:hover:text-slate-200">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <div class="mt-4">
            {{ $slot }}
        </div>
    </div>
</div>
