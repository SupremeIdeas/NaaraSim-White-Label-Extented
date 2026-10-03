{{-- Product showcase card (home). One card per product with generous spacing: icon tile, title (+ badge), description, call to action.
     The product's 3D image is the admin-managed one (Admin → Bento icons, App\Support\BentoIcons): it sits behind the text on the
     right with the opacity and size the admin set for THAT product. Skins restyle the card through the same ns-card hooks. --}}
@props(['bkey', 'href', 'icon' => 'sim', 'title' => null, 'text' => null, 'badge' => null, 'cta' => null])
@php
    $art = \App\Support\BentoIcons::icon($bkey);
    $opacity = \App\Support\BentoIcons::opacityFraction($bkey);
    $scale = \App\Support\BentoIcons::scale($bkey);
@endphp
<a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => 'ns-card ns-ring ns-showcase']) }}>
    <i class="ns-deco" aria-hidden="true"></i>
    @if ($art)
        <img src="{{ $art }}" alt="" aria-hidden="true" class="ns-showcase__art" loading="lazy" decoding="async"
             style="opacity: {{ $opacity }}; --nx-art-scale: {{ $scale }};">
    @endif
    <span class="ns-showcase__head">
        <span class="ns-tile"><x-nx.icon :name="$icon" /></span>
        <h3>{{ $title }}@if ($badge)<x-nx.pill variant="best">{{ $badge }}</x-nx.pill>@endif</h3>
    </span>
    <p>{{ $text }}</p>
    @if ($cta)
        <span class="ns-showcase__cta">{{ $cta }} <x-nx.icon name="chevron-right" /></span>
    @endif
</a>
