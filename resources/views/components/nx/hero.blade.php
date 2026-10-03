{{-- Page hero in the dashboard-home style (nx-home-hero): copy on the left on the plain page background, a photo bleeding in from the top-right and
     dissolving into the page via a mask. No image degrades cleanly to just the headline + description. Light/dark artwork swap with the theme.
     The default slot sits under the description (stat chip, CTA). `size`: sm | md | lg | xl. --}}
@props(['first', 'rest' => '', 'desc' => null, 'kicker' => null, 'light' => null, 'dark' => null, 'size' => 'md'])
@php($titleSize = match ($size) {
    'sm' => 'text-[2rem] sm:text-[2.5rem]',
    'lg' => 'text-[2.75rem] sm:text-[3.75rem]',
    'xl' => 'text-[3rem] sm:text-[4.25rem]',
    default => 'text-[2.5rem] sm:text-[3.25rem]',
})
@php($img = $light ?: $dark)
@php($imgDark = ($dark && $dark !== $img) ? $dark : null)
<section {{ $attributes->merge(['class' => 'nx-home-hero mb-6']) }}>
    @if ($img)
        <div class="nx-home-hero__media" aria-hidden="true">
            <img src="{{ $img }}" alt="" loading="eager" decoding="async" class="nx-home-hero__img {{ $imgDark ? 'dark:hidden' : '' }}">
            @if ($imgDark)<img src="{{ $imgDark }}" alt="" loading="eager" decoding="async" class="nx-home-hero__img hidden dark:block">@endif
        </div>
    @endif
    <div class="relative z-10 max-w-[70%] pt-0.5 sm:max-w-[60%]">
        @if ($kicker)<span class="ns-pg__kicker" style="margin-bottom:6px">{{ $kicker }}</span>@endif
        <h1 class="font-display {{ $titleSize }} font-extrabold leading-[1.07] tracking-[-0.025em]" style="color:rgb(var(--nx-text))">
            @if ($rest !== '')
                {{ $first }}<br><span class="nx-hero-accent">{{ $rest }}</span>
            @else
                <span class="nx-hero-accent">{{ $first }}</span>
            @endif
        </h1>
        @if ($desc)<p class="ns-sub" style="margin-top:14px;max-width:20rem;line-height:1.55">{{ $desc }}</p>@endif
        {{ $slot }}
    </div>
</section>
