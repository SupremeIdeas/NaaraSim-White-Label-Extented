{{-- Swappable LOGIN section — "aurora-shift" style family (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" — deep indigo-violet fintech
     energy, electric-blue highlights, near-black gradient; a trading-
     terminal read on a travel-money app. Keeps the two-column shape (like
     default/midnight-signal/neon-vertex) but replaces every sibling's
     media-panel motif — WebGL planet, colour blooms, scoreboard readout,
     magazine-margin photo — with a literal MOCK TERMINAL WINDOW: a small
     "app chrome" card (window dots + a "NAARA // LIVE RATES" title bar)
     floating over a dot-grid backdrop, holding a vertical auto-scrolling
     rate-ticker tape on one side and a radial "uptime" gauge on the other —
     nobody else on the platform uses a literal window-chrome device or a
     radial SVG gauge. Inherits the exact same variables as
     login/default.blade.php: $panel, $authStyle, $useMedia, $useWebgl,
     $heading, $subheading, $slot (this theme is assigned the 'aurora'
     login_bg effect per the seeder). --}}
@once
    <style>
        @keyframes nx-aurora-ticker-y { 0% { transform: translateY(0); } 100% { transform: translateY(-50%); } }
        .nx-aurora-ticker-track { animation: nx-aurora-ticker-y 14s linear infinite; }
        @media (prefers-reduced-motion: reduce) { .nx-aurora-ticker-track { animation: none; } }
    </style>
@endonce
<div class="flex min-h-screen flex-col bg-navy">
    <div class="flex flex-1 flex-col lg:flex-row">
        {{-- Terminal panel --}}
        <div class="relative flex min-w-0 flex-col justify-between overflow-hidden bg-gradient-to-b from-navy via-[#14163a] to-navy p-8 lg:w-1/2 lg:p-12">
            {{-- Dot-grid backdrop — the terminal's own texture, independent of
                 the admin-assignable login_bg layer behind the form card. --}}
            <div class="pointer-events-none absolute inset-0 opacity-[0.35]" aria-hidden="true"
                 style="background-image: radial-gradient(rgb(var(--brand-accent) / 0.35) 1px, transparent 1px); background-size: 22px 22px;"></div>
            <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-primary/30 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-10 h-80 w-80 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="absolute inset-0 h-full w-full object-cover opacity-30" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif
                       aria-hidden="true">
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true"
                     class="absolute inset-0 h-full w-full object-cover opacity-30">
            @endif

            <div class="relative flex items-center justify-between">
                <a href="{{ route('home') }}" class="inline-flex">
                    <x-brand-logo variant="family" theme="dark" size="lg" fallback-icon="signal" />
                </a>
                <span class="hidden items-center gap-1.5 rounded-[0.625rem] border border-accent/40 bg-accent/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-accent lg:inline-flex">
                    <x-icon name="trending-up" class="h-3 w-3" /> Live
                </span>
            </div>

            <div class="relative hidden lg:block">
                <h2 class="font-display text-3xl font-bold leading-tight text-white xl:text-4xl">{{ $panel['headline'] }}</h2>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300">{{ $panel['subtext'] }}</p>
            </div>

            {{-- The terminal window itself: chrome dots + title bar, a
                 vertical ticker tape and a radial gauge inside. --}}
            <div class="relative mt-8 overflow-hidden rounded-[1.5rem] border border-white/10 bg-navy/80 shadow-2xl backdrop-blur-md lg:mt-10">
                <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-2.5">
                    <span class="h-2 w-2 rounded-full bg-action/70"></span>
                    <span class="h-2 w-2 rounded-full bg-accent/70"></span>
                    <span class="h-2 w-2 rounded-full bg-primary/70"></span>
                    <span class="ml-3 font-display text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">Naara // Live Rates</span>
                </div>
                <div class="grid grid-cols-5">
                    {{-- Vertical auto-scrolling ticker tape. --}}
                    <div class="relative col-span-3 h-32 overflow-hidden border-r border-white/10 px-4 py-3">
                        <div class="nx-aurora-ticker-track">
                            @for ($i = 0; $i < 2; $i++)
                                <div aria-hidden="{{ $i === 1 ? 'true' : 'false' }}">
                                    @foreach ([
                                        ['route' => 'NG → UK', 'val' => '$4.20', 'up' => true],
                                        ['route' => 'KE → US', 'val' => '$6.10', 'up' => false],
                                        ['route' => 'GH → AE', 'val' => '$3.85', 'up' => true],
                                        ['route' => 'ZA → CA', 'val' => '$5.40', 'up' => true],
                                    ] as $row)
                                        <div class="flex items-center justify-between py-1.5 text-xs">
                                            <span class="font-medium text-slate-300">{{ $row['route'] }}</span>
                                            <span class="flex items-center gap-1 font-display font-semibold {{ $row['up'] ? 'text-accent' : 'text-action' }}">
                                                {{ $row['val'] }}
                                                <x-icon name="trending-up" class="h-3 w-3 {{ $row['up'] ? '' : 'rotate-180' }}" />
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endfor
                        </div>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-6 bg-gradient-to-t from-navy/80 to-transparent"></div>
                    </div>

                    {{-- Radial uptime gauge — a stat readout via SVG stroke,
                         not a plain number card like every sibling theme. --}}
                    <div class="col-span-2 flex flex-col items-center justify-center gap-1 px-2 py-3">
                        <svg viewBox="0 0 40 40" class="h-14 w-14 -rotate-90" aria-hidden="true">
                            <circle cx="20" cy="20" r="16" fill="none" stroke="rgb(255 255 255 / 0.1)" stroke-width="4" />
                            <circle cx="20" cy="20" r="16" fill="none" stroke="rgb(var(--brand-accent))" stroke-width="4"
                                    stroke-linecap="round" stroke-dasharray="100.5" stroke-dashoffset="2" pathLength="100.5" />
                        </svg>
                        <p class="font-display text-sm font-bold text-white">99.98%</p>
                        <p class="text-center text-[9px] font-semibold uppercase tracking-wider text-slate-400">Rate uptime</p>
                    </div>
                </div>
            </div>
        </div>

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
