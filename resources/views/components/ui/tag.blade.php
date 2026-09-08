{{-- Status tag/chip (Module 32). Variants: live (pulsing dot) | soon | gold. --}}
@props(['variant' => 'soon'])
<span {{ $attributes->merge(['class' => 'nx-tag nx-tag--'.$variant]) }}>
    <span class="nx-tag__dot" aria-hidden="true"></span>
    {{ $slot }}
</span>
