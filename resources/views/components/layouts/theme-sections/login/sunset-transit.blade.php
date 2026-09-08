{{-- Swappable LOGIN section — "sunset-transit" style family ("Boarding
     Pass", Theme Batch, 2026-09-07). Persona: airline-navy + warm coral.
     Structurally distinct from login/default's plain 50/50 split and
     login/noir-reserve's three-zone magazine-margin composition: the
     WHOLE screen renders as one large BOARDING-PASS TICKET CARD — a wide
     "main coupon" (paper-cream, the actual login form) and a narrower
     "stub" (airline-navy, the photo + flight-style meta), joined by a
     genuine PERFORATED TEAR SEAM: a dashed rule plus two die-cut circular
     notches bitten out of the card edge at each end of the seam (small
     circles coloured to match the page ground, not a clip-path facet). On
     mobile the card stacks vertically and the seam rotates to run
     horizontally, with its own pair of notches, rather than simply
     disappearing.

     Inherits the exact same variables as login/default.blade.php: $panel,
     $authStyle, $useMedia, $useWebgl, $heading, $subheading, $slot. The
     WebGL branded default is intentionally not used here (same precedent
     as login/noir-reserve) — the stub always shows either the admin's
     uploaded media or the shared 'phone-screen' photo, so the panel is
     never an empty gradient box (owner rule: no empty image placeholders).
     Keeps the login_bg hook (`<x-theme-sections.login-bg>`) inside the
     form column's `relative overflow-hidden` wrapper, same contract as
     every other login style family. --}}
<div class="flex min-h-screen flex-col items-center justify-center bg-navy px-4 py-10 sm:px-6 lg:py-16">
    <div class="relative flex w-full max-w-4xl flex-col overflow-hidden rounded-[1.75rem] bg-[#FBF7F0] shadow-2xl dark:bg-[#16233F] md:flex-row">

        {{-- MAIN COUPON — brand, headline, and the real login form. --}}
        <div class="relative flex-1 overflow-hidden px-6 py-8 sm:px-10 sm:py-10">
            <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
            <div class="relative z-10">
                <div class="mb-8 flex items-center justify-between">
                    <a href="{{ route('home') }}"><x-brand-logo variant="family" size="md" fallback-icon="signal" /></a>
                    <x-theme-toggle />
                </div>

                <span class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-primary/30 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-primary dark:border-white/20 dark:text-slate-200">
                    <x-icon name="plane" class="h-3 w-3 -rotate-45" /> Boarding pass
                </span>

                <div class="mt-4 max-w-sm">
                    @if ($heading)
                        <h1 class="font-display text-2xl font-bold text-[#1B2A47] dark:text-white">{{ $heading }}</h1>
                    @endif
                    @if ($subheading)
                        <p class="mt-1 text-sm text-stone-500 dark:text-slate-400">{{ $subheading }}</p>
                    @endif
                </div>

                <div class="mt-6 max-w-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>

        {{-- SEAM + STUB — perforated tear line (vertical on desktop, horizontal
             on mobile) leading into the navy photo stub. --}}
        <div class="relative w-full shrink-0 border-t-2 border-dashed border-white/25 md:w-72 md:border-l-2 md:border-t-0">
            {{-- Desktop: notches at the top and bottom ends of the vertical seam. --}}
            <span class="pointer-events-none absolute -left-2.5 -top-2.5 hidden h-5 w-5 rounded-full bg-navy md:block" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -left-2.5 -bottom-2.5 hidden h-5 w-5 rounded-full bg-navy md:block" aria-hidden="true"></span>
            {{-- Mobile: notches at the left and right ends of the horizontal seam. --}}
            <span class="pointer-events-none absolute -top-2.5 -left-2.5 h-5 w-5 rounded-full bg-navy md:hidden" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -top-2.5 -right-2.5 h-5 w-5 rounded-full bg-navy md:hidden" aria-hidden="true"></span>

            <div class="relative h-56 overflow-hidden md:h-full md:min-h-[26rem]">
                @if ($useMedia && $panel['media_type'] === 'video')
                    <video class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline
                           @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif aria-hidden="true">
                        <source src="{{ $panel['media_url'] }}">
                    </video>
                @elseif ($useMedia)
                    <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
                @else
                    <img src="{{ asset('images/themes/shared/phone-screen.webp') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
                @endif
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy via-navy/70 to-navy/10"></div>

                <div class="relative flex h-full flex-col justify-between p-6">
                    <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">
                        <span>Gate &middot; Anywhere</span>
                        <span>Status &middot; Connected</span>
                    </div>
                    <div>
                        <p class="font-display text-lg font-bold leading-snug text-white">{{ $panel['headline'] }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-white/70">{{ $panel['subtext'] }}</p>
                    </div>
                    {{-- Barcode strip — the boarding-pass finishing touch. --}}
                    <div class="h-6 w-full" aria-hidden="true"
                         style="background-image: repeating-linear-gradient(to right, rgba(255,255,255,0.85) 0 2px, transparent 2px 3px, rgba(255,255,255,0.85) 3px 6px, transparent 6px 8px, rgba(255,255,255,0.85) 8px 9px, transparent 9px 12px, rgba(255,255,255,0.85) 12px 14px, transparent 14px 17px);"></div>
                </div>
            </div>
        </div>
    </div>

    <x-site-footer variant="slim" />
</div>
