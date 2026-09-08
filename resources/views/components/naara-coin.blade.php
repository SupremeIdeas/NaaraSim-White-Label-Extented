@props(['class' => 'h-5 w-5'])

@php
    // NaaraCredits' branded coin (owner request): the brand favicon in a warm-gold
    // ring, so credits read as our own currency. Falls back to a drawn coin glyph
    // when no favicon is set, so it always renders something on-brand.
    $favicon = \App\Support\BrandSettings::favicon();
@endphp

<span {{ $attributes->merge(['class' => $class]) }}
      style="display:inline-flex;align-items:center;justify-content:center;border-radius:9999px;overflow:hidden;background:linear-gradient(135deg,#E9C46A,#D4A017);box-shadow:inset 0 0 0 1.5px rgba(255,255,255,0.35),0 1px 2px rgba(0,0,0,0.15);"
      aria-hidden="true">
    @if ($favicon)
        <img src="{{ $favicon }}" alt="" class="h-[68%] w-[68%] object-contain" style="filter:drop-shadow(0 0 1px rgba(0,0,0,0.15));">
    @else
        <svg viewBox="0 0 24 24" fill="none" class="h-[64%] w-[64%]" stroke="#0D1B2A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="8" /><path d="M12 8v8M9.5 9.8a2.4 2.4 0 0 1 5 .2c0 1.4-1.2 1.9-2.5 2s-2.5.6-2.5 2a2.4 2.4 0 0 0 5 .2" />
        </svg>
    @endif
</span>
