{{-- Payment-brand logo (Module 27.5). Real, saved brand marks — Simple Icons
     (CC0) for the global brands, the providers' own official SVGs for Paystack &
     Flutterwave — rendered inline (CSP-safe) on a white chip so every logo stays
     legible in light and dark. Admin custom-icon overrides (pay.<slug>) win;
     a lettered chip is the last-resort fallback. Nothing here is hand-drawn. --}}
@props(['slug', 'class' => 'h-8'])
@php
    $override = \App\Support\IconOverrides::for('pay.'.$slug);
    $svg = \App\Support\PaymentBrandIcons::svg($slug);
@endphp
@if ($override)
    <img src="{{ $override }}" alt="{{ ucfirst($slug) }} logo"
         {{ $attributes->merge(['class' => $class.' w-auto shrink-0 rounded-lg bg-white object-contain p-1 shadow-sm ring-1 ring-black/5 dark:ring-white/10']) }}>
@elseif ($svg)
    <span {{ $attributes->merge(['class' => $class.' inline-flex w-auto shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white px-2 shadow-sm ring-1 ring-black/5 dark:ring-white/10']) }}
          role="img" aria-label="{{ ucfirst($slug) }} logo">
        <span class="block h-[52%] w-auto [&>svg]:h-full [&>svg]:w-auto">{!! $svg !!}</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => $class.' inline-flex w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 font-display text-sm font-bold text-primary dark:bg-primary/20 dark:text-teal-300']) }}
          role="img" aria-label="{{ ucfirst($slug) }}">{{ strtoupper(substr($slug, 0, 1)) }}</span>
@endif
