{{-- Brand logo (Module 26 + HOTFIX §8). Renders the brand logo for the given
     variant (product = NaaraSim, family = Naara, gift = Naara Gift, agency =
     Supreme Ideas) — admin upload if set, else the shipped default
     (public/brand/*). Swaps light/dark by page theme by default; pass
     `theme="light"|"dark"` to FORCE one on a fixed-background surface.

     SIZING comes from the named `size` prop (sm|md|lg|xl) so the mark reads
     consistently everywhere — the same kind of place uses the same size. The
     `class` attribute is for one-off SPACING utilities (margins) only, applied
     to the wrapper, not for re-inventing the height per usage. --}}
@props(['variant' => 'product', 'size' => 'md', 'fallbackIcon' => 'signal', 'theme' => 'auto', 'label' => null])
@php
    $light = \App\Support\BrandSettings::resolvedLogo($variant, 'light');
    $dark = \App\Support\BrandSettings::resolvedLogo($variant, 'dark');
    // `label` names a sub-brand (e.g. "Naara Gift") for the alt text + wordmark
    // fallback; the platform brand name is the default.
    $name = $label ?: \App\Support\BrandSettings::name();

    // Named sizes → height + max-width. All four official marks (NaaraSim,
    // Naara, Naara Gift, Supreme Ideas Agency) are WIDE wordmarks (~3.4–4.8:1),
    // so one height-based map reads consistently across every variant.
    $wide = [
        'sm' => 'h-7 max-w-[150px]',
        'md' => 'h-8 max-w-[170px]',
        'lg' => 'h-9 max-w-[190px]',
        'xl' => 'h-20 md:h-28',
    ];
    $sizeClass = 'w-auto object-contain '.($wide[$size] ?? $wide['md']);

    // Admin-tunable per-logo scale (Admin → Branding). 1.0 = shipped size; the
    // transform grows/shrinks the mark to the operator's taste without changing
    // the surrounding layout. Anchored left for headers; centred for the xl
    // full-screen entrance.
    $scale = \App\Support\BrandSettings::logoScale($variant);
    $scaleStyle = abs($scale - 1.0) < 0.001 ? '' : 'transform: scale('.$scale.'); transform-origin: '.($size === 'xl' ? 'center' : 'left center').';';
@endphp
@if ($light || $dark)
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center']) }} @if ($scaleStyle) style="{{ $scaleStyle }}" @endif>
        @if ($theme === 'light')
            <img src="{{ $light }}" alt="{{ $name }}" class="block {{ $sizeClass }}">
        @elseif ($theme === 'dark')
            <img src="{{ $dark }}" alt="{{ $name }}" class="block {{ $sizeClass }}">
        @else
            <img src="{{ $light }}" alt="{{ $name }}" class="block dark:hidden {{ $sizeClass }}">
            <img src="{{ $dark }}" alt="{{ $name }}" class="hidden dark:block {{ $sizeClass }}">
        @endif
    </span>
@else
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <x-icon name="{{ $fallbackIcon }}" class="h-6 w-6 text-primary" />
        <span class="font-display text-lg font-bold text-primary-dark dark:text-primary">{{ $name }}</span>
    </span>
@endif
