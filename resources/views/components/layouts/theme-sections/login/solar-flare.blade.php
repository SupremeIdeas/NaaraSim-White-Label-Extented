{{-- Swappable LOGIN section — "solar-flare" style family (Theme Batch 2,
     2026-09-07). Persona: sports-broadcast scoreboard energy. Keeps the
     two-column shape but replaces the branded default panel (WebGL planet /
     glow orbs) with a "live stat ticker" scoreboard readout — a few big
     numbers stacked with small tracked labels, over a diagonal-stripe
     background — since an admin-uploaded image/video still takes priority
     exactly like every other login style family. Inherits the same
     variables as login/default.blade.php: $panel, $authStyle, $useMedia,
     $useWebgl, $heading, $subheading, $slot. --}}
<div class="flex min-h-screen flex-col">
    <div class="flex flex-1 flex-col lg:flex-row">
        {{-- Scoreboard panel --}}
        <div class="relative flex min-w-0 flex-col justify-between overflow-hidden bg-navy p-8 lg:w-1/2 lg:p-12">
            {{-- Diagonal accent stripes — pure CSS, no image, persona motif. --}}
            <div class="pointer-events-none absolute inset-0 opacity-[0.16]" aria-hidden="true"
                 style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 3px, transparent 3px 34px);"></div>
            <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

            @if ($useMedia && $panel['media_type'] === 'video')
                <video class="absolute inset-0 h-full w-full object-cover opacity-40" autoplay muted loop playsinline
                       @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif
                       aria-hidden="true">
                    <source src="{{ $panel['media_url'] }}">
                </video>
            @elseif ($useMedia)
                <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true"
                     class="absolute inset-0 h-full w-full object-cover opacity-40">
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-navy/40 via-navy/60 to-navy"></div>

            <div class="relative flex items-center justify-between">
                <a href="{{ route('home') }}" class="inline-flex">
                    <x-brand-logo variant="family" theme="dark" size="lg" fallback-icon="signal" />
                </a>
                <span class="hidden items-center gap-1.5 border border-accent/60 bg-accent/15 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[0.15em] text-accent [clip-path:polygon(10%_0,100%_0,90%_100%,0_100%)] lg:inline-flex">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent"></span>
                    </span>
                    Live
                </span>
            </div>

            <div class="relative hidden lg:block">
                <h2 class="font-display text-3xl font-bold leading-tight text-white xl:text-4xl">{{ $panel['headline'] }}</h2>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300">{{ $panel['subtext'] }}</p>
            </div>

            {{-- The scoreboard readout itself: big numbers, small tracked labels. --}}
            <div class="relative mt-8 grid grid-cols-3 gap-3 border-t border-white/10 pt-6 lg:mt-10">
                @foreach ([
                    ['value' => '190+', 'label' => 'Countries live'],
                    ['value' => '24/7', 'label' => 'Support on air'],
                    ['value' => '<2min', 'label' => 'To activation'],
                ] as $stat)
                    <div class="border-l-2 border-primary pl-3">
                        <p class="font-display text-2xl font-black leading-none text-white sm:text-3xl">{{ $stat['value'] }}</p>
                        <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
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
                        <h1 class="border-l-4 border-accent pl-3 font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $heading }}</h1>
                    @endif
                    @if ($subheading)
                        <p class="mt-1 pl-3 text-sm text-slate-500 dark:text-slate-400">{{ $subheading }}</p>
                    @endif
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>

    <x-site-footer variant="slim" />
</div>
