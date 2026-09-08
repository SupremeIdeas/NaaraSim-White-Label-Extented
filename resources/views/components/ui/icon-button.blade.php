{{-- Compact circular icon button that expands to reveal a label on hover/focus
     (component-library batch 2 §7). Default use is an inline "Edit" row action,
     but any icon + label works. Renders as an <a> when :href is set, else a
     <button>. Keyboard-reachable: the label also reveals on :focus-visible.
     Attribution: aaronross1 expanding edit button (Uiverse.io, MIT). --}}
@props([
    'icon' => 'edit',
    'label' => 'Edit',
    'href' => null,
    'variant' => 'edit-reveal',
    'type' => 'button',
])
@php($tag = $href ? 'a' : 'button')
<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => 'nx-btn nx-btn--'.$variant]) }}>
    <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
    <span class="nx-btn__label">{{ $label }}</span>
</{{ $tag }}>
