{{-- Swappable LOGIN section — "noir-reserve" style family (Theme visual
     rebuild, brand-new persona, 2026-09-07). Persona: "Espresso-brown with
     a burnt-sienna accent — quiet, tactile, old-money luxury."

     ASYMMETRIC MAGAZINE-MARGIN layout (the pattern earmarked in PROGRESS.md
     for a future batch — this is that batch): NOT a plain 50/50 split like
     login/default, and NOT origin-bold's reversed-proportion two-column
     either. Three zones instead of two:
       1. A wide, empty quiet MARGIN GUTTER (desktop only) — the "magazine
          margin" itself: a hairline rule and a faint rotated masthead
          label, nothing else.
       2. A NARROW form column pushed hard against that gutter (~30% of the
          remaining width, not centred across the viewport).
       3. A large photographic panel filling everything left over, bleeding
          to the right edge, carrying the admin panel headline/subtext in a
          LAYERED GLASS PANEL WITH A SOLID BARRIER LAYER (a translucent
          glass outer card holding a fully solid inner card for real text
          contrast) — the same layered-panel trick earmarked in
          PROGRESS.md, reused here for real contrast instead of the usual
          plain gradient wash + floating text.
     On mobile the gutter collapses entirely and the photo becomes a
     compact strip above the form (order flips to photo-then-form, unlike
     the desktop's gutter-then-form-then-photo, since a magazine margin
     has no meaning on a single column).

     Inherits the exact same variables as login/default.blade.php: $panel,
     $authStyle, $useMedia, $useWebgl, $heading, $subheading, $slot — and
     keeps the login_bg hook (`<x-theme-sections.login-bg>`) inside the
     form column's `relative overflow-hidden` wrapper, same contract as
     every other login style family (this theme is assigned 'mesh-grain'). --}}
<div class="flex min-h-screen flex-col bg-[#F7F1EA] dark:bg-navy">
    <div class="relative flex flex-1 flex-col lg:flex-row">

        {{-- Mobile-only compact photo strip — sits ABOVE the form on a single
             column (photo-then-form), the opposite DOM position from the
             desktop's gutter-then-form-then-photo order below. --}}
        <div class="relative h-36 shrink-0 overflow-hidden lg:hidden">
            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif aria-hidden="true">
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            @else
                <img src="{{ asset('images/themes/shared/coworking-desk.webp') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy/90 via-navy/35 to-navy/10"></div>
        </div>

        {{-- 1. Quiet margin gutter — desktop only. Purely decorative: a
             single hairline and a faint rotated masthead label, echoing a
             printed magazine's outer margin rather than any UI chrome. --}}
        <div class="relative hidden shrink-0 border-r border-accent/15 bg-[#F1E9DF] lg:block lg:w-20 xl:w-28 dark:bg-[#241D1A]" aria-hidden="true">
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="whitespace-nowrap text-[10px] font-semibold uppercase tracking-[0.45em] text-accent-dark/50 dark:text-accent/40"
                      style="writing-mode: vertical-rl; transform: rotate(180deg);">
                    Supreme&nbsp;Ideas&nbsp;Agency
                </span>
            </div>
        </div>

        {{-- 2. Form column — narrow, pushed against the gutter, never
             centred across the full viewport width. --}}
        <div class="relative flex w-full flex-col justify-center overflow-hidden px-6 py-10 sm:px-10 lg:w-[30%] lg:min-w-[22rem] lg:px-12 lg:py-16">
            <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
            <div class="relative z-10 mx-auto w-full max-w-sm lg:mx-0">
                <div class="mb-8 flex items-center justify-between lg:mb-12">
                    <a href="{{ route('home') }}"><x-brand-logo variant="family" size="md" fallback-icon="signal" /></a>
                    <x-theme-toggle />
                </div>

                <div class="mb-8 border-l border-accent/30 pl-4">
                    @if ($heading)
                        <h1 class="font-display text-2xl font-semibold leading-snug text-[#241D1A] dark:text-white">{{ $heading }}</h1>
                    @endif
                    @if ($subheading)
                        <p class="mt-2 text-sm leading-relaxed text-stone-500 dark:text-slate-400">{{ $subheading }}</p>
                    @endif
                </div>

                {{ $slot }}
            </div>
        </div>

        {{-- 3. Photo panel — fills the remaining, larger width, bleeding to
             the edge. Carries the panel headline/subtext as a LAYERED GLASS
             PANEL WITH A SOLID BARRIER LAYER: a translucent glass outer
             card (soft depth, on-brand tint) wrapping a fully solid inner
             card that actually holds the text, for real contrast rather
             than a thin gradient wash. --}}
        <div class="relative hidden flex-1 overflow-hidden lg:block">
            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif aria-hidden="true">
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            @else
                <img src="{{ asset('images/themes/shared/coworking-desk.webp') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-navy/85 via-navy/45 to-navy/80"></div>

            <a href="{{ route('home') }}" class="relative z-10 inline-flex p-10 xl:p-14">
                <x-brand-logo variant="family" theme="dark" size="lg" fallback-icon="signal" />
            </a>

            <div class="absolute inset-x-10 bottom-10 z-10 xl:inset-x-14 xl:bottom-14">
                {{-- Glass outer layer. --}}
                <div class="rounded-xl border border-white/15 bg-white/10 p-1.5 shadow-2xl backdrop-blur-md">
                    {{-- Solid inner "barrier" layer — real contrast for the text. --}}
                    <div class="rounded-lg bg-[#241D1A]/95 p-6 xl:p-8">
                        <p class="font-display text-2xl font-semibold leading-snug text-white xl:text-3xl">{{ $panel['headline'] }}</p>
                        <p class="mt-3 max-w-md text-sm leading-relaxed text-white/70">{{ $panel['subtext'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-site-footer variant="slim" />
</div>
