{{-- Swappable LOGIN section — "origin-bold" style family (Theme Batch 1,
     2026-09-07). Persona: "Vivid construction-orange with graphite accents,
     oversized type, confident colour blocks." Keeps the two-column shape but
     REVERSES the proportions (form leads at 60%, media block trails at 40%)
     and the media panel is a solid colour block with an oversized numeral
     motif rather than a photographic/WebGL scene. Inherits the same
     variables as login/default.blade.php. --}}
<div class="flex min-h-screen flex-col">
    <div class="flex flex-1 flex-col lg:flex-row-reverse">
        {{-- Colour-block panel (trails on desktop, 40% width). `min-w-0` on
             both this panel and the text block below is required — flex
             items default to `min-width: auto`, which lets long unwrapped
             text force the panel wider than its intended 40% instead of
             wrapping inside it. --}}
        <div class="relative flex min-w-0 items-center justify-center overflow-hidden bg-primary lg:w-2/5">
            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="absolute inset-0 h-full w-full object-cover opacity-50" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif
                       aria-hidden="true">
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true"
                     class="absolute inset-0 h-full w-full object-cover opacity-50">
            @else
                {{-- Branded default for this persona: an oversized outlined
                     numeral, a confident colour-block motif in place of the
                     shared WebGL planet scene. Absolutely positioned so its
                     huge font size never affects the flex layout above. --}}
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden" aria-hidden="true">
                    <span class="select-none font-display text-[14rem] font-black leading-none text-navy/10">190+</span>
                </div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy/40 via-transparent to-transparent"></div>
            <div class="relative hidden w-full min-w-0 max-w-xs flex-col items-center gap-3 p-8 text-center lg:flex">
                <p class="font-display text-2xl font-bold leading-tight text-white">{{ $panel['headline'] }}</p>
                <p class="text-sm leading-relaxed text-white/85">{{ $panel['subtext'] }}</p>
            </div>
        </div>

        {{-- Form column (leads on desktop, 60% width) --}}
        <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-[#F8F9FA] px-4 py-10 dark:bg-navy lg:w-3/5">
            <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
            <div class="relative z-10 w-full max-w-md">
                <div class="mb-6 flex items-center justify-between">
                    <a href="{{ route('home') }}"><x-brand-logo variant="family" size="lg" fallback-icon="signal" /></a>
                    <x-theme-toggle />
                </div>

                <div class="mb-6 border-l-4 border-primary pl-4">
                    @if ($heading)
                        <h1 class="font-display text-3xl font-black text-slate-900 dark:text-white">{{ $heading }}</h1>
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
