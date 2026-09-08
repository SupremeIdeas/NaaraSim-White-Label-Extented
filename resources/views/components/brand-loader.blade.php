{{--
     Scoped action loader (audit section 7, mode 2). The pulsing-logo motif at a
     small in-card size, tied to a Livewire action: it appears while the action
     is in flight and dismisses EXACTLY when the action resolves — Livewire
     toggles it via wire:loading / wire:loading.remove, so "the loader dismisses
     alongside" the finished section. Falls back to the brand orbit loader if no
     favicon is set. Reduced-motion is handled by the shared .nx-pulse CSS.

     Props: target (wire:target action), size (logo px), label, overlay (bool —
     absolutely fills the nearest positioned ancestor). Do NOT put a literal
     component tag in this comment: Blade compiles component tags even inside
     comments, and a self-reference here would recurse infinitely.
--}}
@props([
    'target' => null,       // wire:target action(s); omit to react to any action on the component
    'size' => 44,           // logo px
    'label' => 'Working…',
    'overlay' => false,     // absolutely-fill the nearest positioned ancestor
])
@php($fav = \App\Support\BrandSettings::favicon())

<div
    wire:loading
    @if ($target) wire:target="{{ $target }}" @endif
    role="status" aria-live="polite"
    {{ $attributes->merge(['class' => $overlay
        ? 'absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-[inherit] bg-white/90 dark:bg-[#0D1B2A]/90'
        : 'inline-flex items-center gap-2']) }}>
    @if ($fav)
        <span class="nx-pulse" style="width:{{ $size }}px;height:{{ $size }}px;">
            <img src="{{ $fav }}" alt="" width="{{ $size }}" height="{{ $size }}" draggable="false">
        </span>
    @else
        <span class="nx-loader" style="--size:{{ $size / 16 }}rem"></span>
    @endif
    @if ($label)
        <span class="text-sm font-medium text-slate-600 dark:text-slate-100">{{ $label }}</span>
    @endif
</div>
