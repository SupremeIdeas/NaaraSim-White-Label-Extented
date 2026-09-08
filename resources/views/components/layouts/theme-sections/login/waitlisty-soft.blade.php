{{-- Swappable LOGIN section — "waitlisty-soft" style family ("Horizon",
     Theme visual rebuild). Persona: soft violet-purple with a magenta-pink
     pop, rounded-everything, warm consumer feel. Structurally different
     from every other login style: not a side-by-side media-panel split
     (default/midnight-signal/neon-vertex) and not a single bordered
     sharp-cornered card (aries-contrast) — a single STACKED column reading
     top-to-bottom like a friendly mobile "welcome" screen: a gradient blob
     band up top, a circular photo/avatar badge overlapping the seam, then
     a big soft rounded card holding the real form. Ignores $useWebgl (no
     WebGL scene fits this playful, illustration-led persona) but still
     honours an admin-uploaded $panel image/video in the circular badge.
     Inherits the same variables as login/default.blade.php: $panel,
     $useMedia, $heading, $subheading, $slot. --}}
<div class="relative flex min-h-screen flex-col overflow-hidden bg-[#FBF7FF] dark:bg-navy">
    <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />

    {{-- Gradient welcome band --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-primary via-primary to-accent px-4 pb-20 pt-8 sm:pt-10">
        <span class="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></span>
        <span class="pointer-events-none absolute -right-10 bottom-0 h-48 w-48 rounded-[40%_60%_65%_35%/45%_40%_60%_55%] bg-accent-dark/30 blur-2xl" aria-hidden="true"></span>

        <div class="relative mx-auto flex max-w-sm items-center justify-between">
            <a href="{{ route('home') }}" class="inline-flex"><x-brand-logo variant="family" theme="dark" size="md" fallback-icon="signal" /></a>
            <x-theme-toggle />
        </div>

        <div class="relative mx-auto mt-8 max-w-sm text-center">
            @if (! empty($panel['headline']))
                <p class="font-display text-xl font-bold leading-snug text-white sm:text-2xl">{{ $panel['headline'] }}</p>
            @endif
            @if (! empty($panel['subtext']))
                <p class="mt-2 text-sm leading-relaxed text-white/85">{{ $panel['subtext'] }}</p>
            @endif
        </div>
    </div>

    {{-- Circular badge overlapping the seam between the band and the card. --}}
    <div class="relative z-20 mx-auto -mt-11 flex justify-center px-4" aria-hidden="true">
        <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full ring-4 ring-[#FBF7FF] shadow-lg dark:ring-navy">
            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="h-full w-full object-cover" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif>
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" class="h-full w-full object-cover">
            @else
                <span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary to-accent text-white">
                    <x-icon name="sparkles" class="h-8 w-8" />
                </span>
            @endif
        </div>
    </div>

    {{-- Form card --}}
    <div class="relative z-10 -mt-4 flex flex-1 justify-center px-4 pb-10">
        <div class="w-full max-w-sm rounded-[2rem] bg-white p-6 pt-9 shadow-[0_20px_50px_-24px_rgba(109,63,160,0.5)] dark:bg-[#1c1329] sm:p-8 sm:pt-10">
            {{-- The theme toggle already sits in the band above (shown at
                 every breakpoint, since this layout stays single-column) —
                 no second toggle is needed here. --}}
            <div class="mb-6 text-center">
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

    <x-site-footer variant="slim" />
</div>
