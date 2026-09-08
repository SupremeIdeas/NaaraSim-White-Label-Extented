{{-- Swappable LOGIN section — "midnight-signal" style family (Theme Batch 1,
     2026-09-07). Persona: "Near-black smart-home dark mode, cyan-teal
     accents, dark-first design." Keeps the default's two-column shape but
     swaps the branded-default media panel for a HUD/radar motif (concentric
     rings + scanning sweep) instead of the WebGL planet + orbit flags, and
     the form column gets a console-style bordered input area. Inherits the
     same variables as login/default.blade.php. --}}
<div class="flex min-h-screen flex-col">
    <div class="flex flex-1 flex-col lg:flex-row">
        {{-- Media panel --}}
        <div class="relative overflow-hidden bg-navy lg:w-1/2">
            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="absolute inset-0 h-full w-full object-cover opacity-60" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif
                       aria-hidden="true">
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true"
                     class="absolute inset-0 h-full w-full object-cover opacity-60">
            @else
                {{-- Branded default for this persona: concentric radar rings
                     centred on a pulsing signal dot, cyan-on-navy — a HUD
                     motif in place of the shared WebGL planet scene. --}}
                <div class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <span class="absolute h-56 w-56 rounded-full border border-primary/20"></span>
                    <span class="absolute h-80 w-80 rounded-full border border-primary/15"></span>
                    <span class="absolute h-[26rem] w-[26rem] animate-ping rounded-full border border-primary/10" style="animation-duration:3s"></span>
                    <span class="relative flex h-3 w-3 rounded-full bg-primary shadow-lg shadow-primary/70"></span>
                </div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-navy/70 via-navy/20 to-navy/80"></div>

            <div class="relative flex h-full flex-col justify-between p-8 lg:p-12">
                <a href="{{ route('home') }}" class="inline-flex">
                    <x-brand-logo variant="family" theme="dark" size="lg" fallback-icon="signal" />
                </a>
                <div class="hidden lg:block">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.25em] text-primary">Signal locked</p>
                    <h2 class="font-display text-3xl font-bold leading-tight text-white xl:text-4xl">{{ $panel['headline'] }}</h2>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300">{{ $panel['subtext'] }}</p>
                </div>
                <p class="hidden text-xs font-medium uppercase tracking-widest text-slate-400 lg:block">Supreme Ideas Agency</p>
            </div>
        </div>

        {{-- Form column --}}
        <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-[#F8F9FA] px-4 py-10 dark:bg-black lg:w-1/2">
            <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
            <div class="relative z-10 w-full max-w-sm rounded-2xl border border-primary/15 bg-white p-6 dark:border-primary/20 dark:bg-navy/60">
                <div class="mb-6 flex items-center justify-between lg:hidden">
                    <a href="{{ route('home') }}"><x-brand-logo variant="family" size="md" fallback-icon="signal" /></a>
                    <x-theme-toggle />
                </div>

                <div class="mb-6">
                    <div class="mb-4 hidden justify-end lg:flex"><x-theme-toggle /></div>
                    @if ($heading)
                        <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $heading }}</h1>
                    @endif
                    @if ($subheading)
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subheading }}</p>
                    @endif
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>

    <x-site-footer variant="slim" />
</div>
