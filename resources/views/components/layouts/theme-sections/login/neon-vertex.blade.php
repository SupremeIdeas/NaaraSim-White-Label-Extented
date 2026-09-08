{{-- Swappable LOGIN section — "neon-vertex" style family (Theme Batch 1,
     2026-09-07). Persona: "Deep ultraviolet with a hot-pink flash on
     near-black — nightlife, electronic, after-hours energy." Keeps the
     two-column shape but replaces the straight seam with a skewed neon
     accent strip across the boundary, and the media panel's default motif
     is soft overlapping colour blooms rather than the shared WebGL planet.
     Inherits the same variables as login/default.blade.php. --}}
<div class="flex min-h-screen flex-col">
    <div class="relative flex flex-1 flex-col lg:flex-row">
        {{-- Media panel --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-navy via-primary to-accent lg:w-1/2">
            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="absolute inset-0 h-full w-full object-cover opacity-70" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif
                       aria-hidden="true">
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true"
                     class="absolute inset-0 h-full w-full object-cover opacity-70">
            @else
                {{-- Branded default for this persona: overlapping colour
                     blooms instead of the shared WebGL planet scene. --}}
                <span class="pointer-events-none absolute -left-10 -top-10 h-72 w-72 rounded-full bg-accent/40 blur-3xl"></span>
                <span class="pointer-events-none absolute -bottom-16 right-0 h-80 w-80 rounded-full bg-primary/40 blur-3xl"></span>
                <span class="pointer-events-none absolute left-1/3 top-1/2 h-56 w-56 -translate-y-1/2 rounded-full bg-white/10 blur-2xl"></span>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-navy/50 via-transparent to-navy/60"></div>

            <div class="relative flex h-full flex-col justify-between p-8 lg:p-12">
                <a href="{{ route('home') }}" class="inline-flex">
                    <x-brand-logo variant="family" theme="dark" size="lg" fallback-icon="signal" />
                </a>
                <div class="hidden lg:block">
                    <h2 class="font-display text-3xl font-bold leading-tight text-white xl:text-4xl">{{ $panel['headline'] }}</h2>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-white/80">{{ $panel['subtext'] }}</p>
                </div>
                <p class="hidden text-xs font-medium uppercase tracking-widest text-white/70 lg:block">Supreme Ideas Agency</p>
            </div>
        </div>

        {{-- Skewed neon seam accent across the column boundary (desktop only). --}}
        <div class="pointer-events-none absolute inset-y-0 left-1/2 hidden w-20 -translate-x-1/2 skew-x-12 bg-gradient-to-b from-accent/70 via-primary/40 to-transparent lg:block" aria-hidden="true"></div>

        {{-- Form column --}}
        <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-[#F8F9FA] px-4 py-10 dark:bg-navy lg:w-1/2">
            <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
            <div class="relative z-10 w-full max-w-sm">
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
