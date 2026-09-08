@php($heroLight = \App\Support\HeroBackground::light())
@php($heroDark = \App\Support\HeroBackground::dark())
@php($hasHero = \App\Support\HeroBackground::showsOnDashboard())
@php($heroDesc = \App\Support\HeroBackground::description())
{{-- Per-theme home hero (owner request): each theme carries its own hero image.
     An admin-uploaded HeroBackground still wins; otherwise the active theme's
     hero shows. --}}
@php($themeHero = \App\Support\ThemePreset::heroFor('dashboard'))
@php($heroImg = ($hasHero ? ($heroLight ?: $heroDark) : null) ?: $themeHero)
@php($heroImgDark = ($hasHero && $heroDark) ? $heroDark : $themeHero)
{{-- Admin-overridable + resizable headline (owner request). Defaults to the
     shipped "My Connectivity" at the shipped size, so an untouched install
     renders byte-identical to before. --}}
@php($heroTitleFirst = \App\Support\HeroBackground::titleFirstWord())
@php($heroTitleRest = \App\Support\HeroBackground::titleRestWords())
{{-- Literal classes here (not composed in the PHP support class) so Tailwind's
     content scanner — which only reads resources/**/*.blade.php — actually sees
     them; a string built only inside app/**/*.php would be silently purged. --}}
@php($heroTitleSizeClasses = match (\App\Support\HeroBackground::titleSize()) {
    'sm' => 'text-[2rem] sm:text-[2.5rem]',
    'lg' => 'text-[2.75rem] sm:text-[3.75rem]',
    'xl' => 'text-[3rem] sm:text-[4.25rem]',
    default => 'text-[2.5rem] sm:text-[3.25rem]', // 'md' — the shipped default
})
{{-- Admin-overridable CTA button size (owner request). 'md' matches the
     shipped size exactly, so an untouched install renders byte-identical. --}}
@php($heroCtaPillClasses = match (\App\Support\HeroBackground::ctaSize()) {
    'sm' => 'px-3.5 py-2 text-xs sm:px-4 sm:py-2.5 sm:text-sm',
    'lg' => 'px-5 py-3 text-sm sm:px-6 sm:py-4 sm:text-lg',
    default => 'px-4 py-2.5 text-[13px] sm:px-5 sm:py-3.5 sm:text-base', // 'md'
})
@php($heroCtaIconClasses = match (\App\Support\HeroBackground::ctaSize()) {
    'sm' => 'h-3.5 w-3.5 sm:h-4 sm:w-4',
    'lg' => 'h-4 w-4 sm:h-[22px] sm:w-[22px]',
    default => 'h-4 w-4 sm:h-5 sm:w-5', // 'md'
})

{{--
    Dashboard home hero (reference-matched). Text sits LEFT; the photo bleeds into
    the top-right corner with NO card and NO border, dissolving into the page
    background on its left + bottom edges via a mask (faint over the light bg, a
    deeper fade over dark). Two pill CTAs below. When no image is set it degrades
    cleanly to the headline + pills with no empty gap.
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

    <div class="relative z-10 max-w-[60%] pt-0.5 sm:max-w-[56%]">
        <h1 class="font-display {{ $heroTitleSizeClasses }} font-extrabold leading-[1.07] tracking-[-0.025em] text-slate-900 dark:text-white">
            {{-- from-primary to-accent (not a hardcoded teal) so the gradient
                 repaints with the active theme — same --brand-* vars every
                 other themed gradient on the platform already uses. --}}
            @if ($heroTitleRest !== '')
                {{ $heroTitleFirst }}<br>
                <span class="bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent">{{ $heroTitleRest }}</span>
            @else
                <span class="bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent">{{ $heroTitleFirst }}</span>
            @endif
        </h1>
        <p class="mt-3.5 max-w-[16rem] text-[15px] leading-relaxed text-slate-500 dark:text-slate-300 sm:text-base">{{ $heroDesc }}</p>
    </div>

    {{-- CTA row sits OUTSIDE the headline's narrow max-w column (owner
         report: the two pills were stacking on mid-width viewports because
         they were squeezed into the same 56-60% column reserved for the
         bleeding photo). The photo's own mask already fades out well above
         this row ("clearing the CTA row" — see .nx-home-hero__img above), so
         giving the buttons the FULL hero width here doesn't collide with it.
         shrink-0 + whitespace-nowrap on each pill still stops the label text
         itself wrapping mid-word. --}}
    <div class="relative z-10 mt-7 flex flex-wrap items-center gap-2 sm:gap-2.5">
        <a href="{{ route('catalogue') }}" wire:navigate
           class="group inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full bg-gradient-to-br from-primary via-primary-dark to-navy {{ $heroCtaPillClasses }} font-bold text-white shadow-lg shadow-primary/25 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/35">
            <x-icon name="sim" class="{{ $heroCtaIconClasses }}" /> Buy eSIM
            <x-icon name="chevron-right" class="hidden h-4 w-4 transition-transform group-hover:translate-x-0.5 sm:inline-block" />
        </a>
        <a href="{{ route('numbers') }}" wire:navigate
           class="group inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-slate-200 bg-white {{ $heroCtaPillClasses }} font-bold text-slate-900 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-[#16233d] dark:text-white">
            <x-icon name="hash" class="{{ $heroCtaIconClasses }} text-primary dark:text-teal-300" /> Get Number
            <x-icon name="chevron-right" class="hidden h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5 sm:inline-block" />
        </a>
    </div>
</section>
