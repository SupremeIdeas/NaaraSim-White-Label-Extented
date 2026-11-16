@props(['name', 'class' => 'w-5 h-5', 'gradient' => false])
@php
    // Admin custom-icon override (blueprint Section 16.2): if the admin has
    // mapped an image URL for this icon name, render the image; otherwise use
    // the built-in inline sprite symbol.
    $custom = \App\Support\IconOverrides::for($name);
    // Premium two-tone brand gradient (owner request) when :gradient is set.
    $gradClass = $gradient ? ' nx-grad-icon' : '';
@endphp
@if ($custom)
    <img src="{{ $custom }}" alt="{{ $name }}"
         {{ $attributes->merge(['class' => $class.' object-contain inline-block']) }}>
@else
    @php($__duo = $gradient ? null : \App\Support\DuoIcons::get(\Illuminate\Support\Str::slug($name)))
    @if ($__duo)
    {{-- Hand-drawn two-colour icon: ink stroke follows the surrounding text colour, the soft accent shape is offset behind it. --}}
    <svg {{ $attributes->merge(['class' => $class.' nx-duo nx-duo--drawn']) }} viewBox="0 0 24 24" fill="none" aria-hidden="true" style="overflow:visible">
        <g class="nx-duo__f" transform="translate(1.700 1.700)" fill="rgb(var(--nx-gold, 212 160 23))" fill-opacity=".38" stroke="none">{!! $__duo[1] !!}</g>
        <g stroke="currentColor" stroke-width="1.700" stroke-linecap="round" stroke-linejoin="round">{!! $__duo[0] !!}</g>
    </svg>
    @else
    <svg {{ $attributes->merge(['class' => $class.$gradClass]) }} fill="none" aria-hidden="true">
        <use href="#i-{{ \Illuminate\Support\Str::slug($name) }}"></use>
    </svg>
    @endif
@endif
