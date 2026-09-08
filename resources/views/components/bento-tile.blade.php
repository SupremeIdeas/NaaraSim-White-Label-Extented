{{-- Bento action tile (owner request → premium vertical refresh). A bento card
     that replaces the old pill buttons: a 3D illustrated icon sitting ABOVE a
     bold label, centered — "icon up, text down" for a premium, app-store feel
     (not the old icon-beside-text row). The icon + its opacity/size are
     admin-managed (Admin → Bento icons) via App\Support\BentoIcons, so they
     change with no redeploy.

     Renders an <a> when `href` is given (with wire:navigate), else a <button>.
     Every other directive — wire:click, @click, extra classes — passes straight
     through the attribute bag, so the caller keeps its exact click/nav logic.

     variant: "primary" = filled teal card · "default" = solid white/outlined.  --}}
@props(['bkey', 'label', 'href' => null, 'variant' => 'default'])
@php
    $icon = \App\Support\BentoIcons::icon($bkey);
    $op = \App\Support\BentoIcons::opacityFraction($bkey);
    // Admin-tunable size (Admin → Bento icons). Base 44px × scale; floored a
    // touch larger here so the icon reads as the hero of the centered card.
    $iconPx = max(64, (int) round(44 * \App\Support\BentoIcons::scale($bkey)));

    $base = 'nx-bento-tile group relative flex flex-col items-center justify-center gap-3 overflow-hidden rounded-2xl border p-5 text-center transition-all duration-300 hover:-translate-y-1 sm:p-6';
    $skin = $variant === 'primary'
        ? 'border-primary/30 bg-gradient-to-b from-primary/10 to-primary/5 text-primary shadow-sm hover:shadow-lg hover:shadow-primary/10 dark:border-primary/40 dark:from-primary/20 dark:to-primary/10 dark:text-teal-200'
        : 'border-slate-200 bg-white text-slate-800 shadow-sm hover:bg-slate-50 hover:shadow-lg dark:border-white/10 dark:bg-[#16233d] dark:text-slate-100 dark:hover:bg-[#1b2c49]';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base.' '.$skin]) }}>
@else
    <button type="button" {{ $attributes->merge(['class' => $base.' '.$skin]) }}>
@endif
        {{-- Ambient brand glow, pooled behind the centered icon. --}}
        <span class="pointer-events-none absolute left-1/2 top-2 h-28 w-28 -translate-x-1/2 rounded-full bg-primary/10 blur-2xl transition-all duration-300 group-hover:bg-primary/20 dark:bg-teal-500/15 dark:group-hover:bg-teal-500/25" aria-hidden="true"></span>

        @if ($icon)
            {{-- 3D icon on a soft radial pedestal — floats up + brightens on hover. --}}
            <span class="relative flex items-center justify-center" aria-hidden="true">
                <span class="absolute inset-0 -z-[1] rounded-full bg-[radial-gradient(circle_at_50%_45%,rgba(10,110,110,0.18),transparent_70%)] blur-md dark:bg-[radial-gradient(circle_at_50%_45%,rgba(45,212,191,0.22),transparent_70%)]"></span>
                <img src="{{ $icon }}" alt=""
                     class="relative shrink-0 object-contain drop-shadow-[0_6px_14px_rgba(13,27,42,0.18)] transition-transform duration-300 ease-out group-hover:-translate-y-0.5 group-hover:scale-[1.07]"
                     style="width: {{ $iconPx }}px; height: {{ $iconPx }}px; opacity: {{ $op }};">
            </span>
        @endif

        <span class="relative text-sm font-bold leading-snug tracking-tight sm:text-base">{{ $label }}</span>
@if ($href)
    </a>
@else
    </button>
@endif
