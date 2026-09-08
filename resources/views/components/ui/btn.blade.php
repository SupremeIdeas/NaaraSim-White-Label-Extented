{{-- Premium branded button (Module 32). Variants: primary | gold | ghost |
     danger. Keeps the platform loading-state rule: pass wire:target via the
     `target` prop and the button disables + spins while in flight. --}}
@props(['variant' => 'primary', 'type' => 'submit', 'target' => null, 'icon' => null])
<button type="{{ $type }}"
        @if ($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif
        {{ $attributes->merge(['class' => 'nx-btn nx-btn--'.$variant]) }}>
    @if ($target)
        <span wire:loading.remove wire:target="{{ $target }}" class="inline-flex items-center gap-2">
            @if ($icon)<x-icon :name="$icon" class="h-4 w-4" />@endif
            {{ $slot }}
        </span>
        <span wire:loading wire:target="{{ $target }}" class="inline-flex items-center gap-2">
            <span class="nx-loader" style="--size: 1rem"></span> Working…
        </span>
    @else
        @if ($icon)<x-icon :name="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    @endif
</button>
