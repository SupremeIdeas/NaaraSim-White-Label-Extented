{{-- Self-hosted Lottie animation (resources/js/lottie.js hydrates every
     [data-lottie] node — runtime + JSON are lazy, CSP-safe, reduced-motion aware).
     `name` must match a key in the JS REGISTRY (gift-preloader | reward |
     refer-earn | rewards-confetti | merchant-v1-badge | merchant-v2-badge |
     merchant-hero). --}}
@props(['name', 'loop' => true, 'autoplay' => true, 'label' => null])
<div
    data-lottie="{{ $name }}"
    data-lottie-loop="{{ $loop ? 'true' : 'false' }}"
    data-lottie-autoplay="{{ $autoplay ? 'true' : 'false' }}"
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
    {{-- `block` only — the caller sizes it (e.g. class="h-48 w-48"); we must not
         force w-full/h-full or it overrides the caller's size and renders huge. --}}
    {{ $attributes->merge(['class' => 'block']) }}
></div>
