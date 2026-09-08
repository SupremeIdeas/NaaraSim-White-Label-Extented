@php($heroLight = \App\Support\GiftHeroBackground::light())
@php($heroDark = \App\Support\GiftHeroBackground::dark())
@php($showsImage = \App\Support\GiftHeroBackground::showsImage())
@php($heroDesc = \App\Support\GiftHeroBackground::description())
@php($heroImg = $showsImage ? ($heroLight ?: $heroDark) : null)
@php($heroImgDark = ($showsImage && $heroDark) ? $heroDark : null)
{{-- Admin-overridable + resizable headline, same system as the dashboard home
     hero (owner request) so this can be re-themed independently later.
     Defaults to "Naara Gift" so an untouched install renders a complete hero. --}}
@php($heroTitleFirst = \App\Support\GiftHeroBackground::titleFirstWord())
@php($heroTitleRest = \App\Support\GiftHeroBackground::titleRestWords())
{{-- Literal classes here (not composed in the PHP support class) so Tailwind's
     content scanner — which only reads resources/**/*.blade.php — actually sees
     them; see HeroBackground's own note on this. --}}
@php($heroTitleSizeClasses = match (\App\Support\GiftHeroBackground::titleSize()) {
    'sm' => 'text-[2rem] sm:text-[2.5rem]',
    'lg' => 'text-[2.75rem] sm:text-[3.75rem]',
    'xl' => 'text-[3rem] sm:text-[4.25rem]',
    default => 'text-[2.5rem] sm:text-[3.25rem]', // 'md' — the shipped default
})

{{--
    Naara Gift storefront hero — the exact same treatment as the dashboard home
    hero (nx-home-hero): text sits LEFT, the photo bleeds into the top-right
    corner with no card/border, dissolving into the page background via a mask.
    No image set degrades cleanly to the headline + description with no gap.
--}}
<section class="nx-home-hero mb-6">
    @if ($heroImg)
        <div class="nx-home-hero__media" aria-hidden="true">
            <img src="{{ $heroImg }}" alt="" loading="eager" decoding="async"
                 class="nx-home-hero__img {{ ($heroImgDark && $heroImgDark !== $heroImg) ? 'dark:hidden' : '' }}">
            @if ($heroImgDark && $heroImgDark !== $heroImg)
                <img src="{{ $heroImgDark }}" alt="" loading="eager" decoding="async"
                     class="nx-home-hero__img hidden dark:block">
            @endif
        </div>
    @endif

    <div class="relative z-10 max-w-[70%] pt-0.5 sm:max-w-[60%]">
        <h1 class="font-display {{ $heroTitleSizeClasses }} font-extrabold leading-[1.07] tracking-[-0.025em] text-slate-900 dark:text-white">
            @if ($heroTitleRest !== '')
                {{ $heroTitleFirst }}<br>
                <span class="nx-hero-accent">{{ $heroTitleRest }}</span>
            @else
                <span class="nx-hero-accent">{{ $heroTitleFirst }}</span>
            @endif
        </h1>
        <p class="mt-3.5 max-w-xs text-[15px] leading-relaxed text-slate-500 dark:text-slate-300 sm:text-base">{{ $heroDesc }}</p>
    </div>
</section>
