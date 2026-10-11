{{-- NaaraCredits coin (owner artwork). A self-hosted, ~4 KB / ~10 KB WebP with a clean alpha edge, so credits read as our own currency on
     every skin, light or dark. Two committed sizes: the 64px file serves the small inline uses (up to ~20px, 3x-dense screens included),
     the 128px file everything larger. Size comes from the class (h-*/w-*); the image is decorative (aria-hidden) because the number or
     label beside it carries the meaning. --}}
@props(['class' => 'h-5 w-5'])
@php
    // Small inline uses (h-3 … h-5) get the 64px file; anything bigger gets the 128px one.
    $small = preg_match('/(?:^|\s)h-(?:[0-4]|0\.5|1\.5|2\.5|3\.5)(?:\s|$)/', (string) $class) === 1 || preg_match('/(?:^|\s)h-5(?:\s|$)/', (string) $class) === 1;
    $file = $small ? 'naara-coin-64.webp' : 'naara-coin-128.webp';
@endphp
<img src="{{ asset('images/brand/'.$file) }}" alt="" aria-hidden="true" width="{{ $small ? 64 : 128 }}" height="{{ $small ? 64 : 128 }}"
     loading="lazy" decoding="async" draggable="false"
     {{ $attributes->merge(['class' => $class.' inline-block shrink-0 select-none object-contain align-middle']) }}
     style="filter:drop-shadow(0 1px 1.5px rgb(0 0 0 / .22))">
