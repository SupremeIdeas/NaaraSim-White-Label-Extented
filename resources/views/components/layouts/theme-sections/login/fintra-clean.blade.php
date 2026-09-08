{{-- Swappable LOGIN section — "fintra-clean" style family ("Ledger" persona,
     Theme visual rebuild, 2026-09-07). Keeps the default's two-column shape
     but the media panel's branded default is a literal MINI STATEMENT MOCK
     — line items, a dashed rule, a gold-underlined total, tabular-nums
     figures — floating on a slate-blue gradient, instead of the WebGL
     planet (default), colour blooms (neon-vertex), radar rings (midnight-
     signal), an oversized numeral (origin-bold), a scoreboard stat readout
     (solar-flare), or a magazine-margin photo (noir-reserve). No other
     login style on the platform renders a fake UI element like this.
     Inherits the same variables as login/default.blade.php: $panel,
     $authStyle, $useMedia, $useWebgl, $heading, $subheading, $slot. --}}
<div class="flex min-h-screen flex-col">
    <div class="flex flex-1 flex-col lg:flex-row">
        {{-- Media panel --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-primary via-primary-dark to-navy lg:w-1/2">
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
                {{-- Branded default for this persona: a mini statement mock
                     — real ledger content (line items + a gold-underlined
                     total), never a decorative blob/orb/glow. --}}
                <div class="pointer-events-none absolute inset-0 flex items-start justify-end p-8 pt-24 lg:p-12 lg:pt-28" aria-hidden="true">
                    <div class="w-full max-w-[240px] rounded-xl border border-white/15 bg-white/[0.07] p-5 shadow-2xl backdrop-blur-sm">
                        <div class="flex items-center justify-between border-b border-dashed border-white/25 pb-3">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/60">Statement No. 00214</span>
                            <span class="inline-flex items-center gap-1 rounded-md bg-accent/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                                <x-icon name="check" class="h-2.5 w-2.5" /> Reconciled
                            </span>
                        </div>
                        <div class="mt-3 space-y-2.5">
                            @foreach ([['Nigeria · 5GB eSIM', '$8.40'], ['US virtual number', '$2.10'], ['SMS verification x3', '$0.90']] as [$rowLabel, $rowAmt])
                                <div class="flex items-center justify-between text-xs text-white/80">
                                    <span>{{ $rowLabel }}</span>
                                    <span class="font-mono tabular-nums">{{ $rowAmt }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t-2 border-accent pt-3">
                            <span class="text-xs font-bold uppercase tracking-wide text-white">Total</span>
                            <span class="font-mono text-base font-bold tabular-nums text-accent">$11.40</span>
                        </div>
                    </div>
                </div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-navy/40 via-transparent to-navy/65"></div>

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

        {{-- Form column --}}
        <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-[#F8F9FA] px-4 py-10 dark:bg-navy lg:w-1/2">
            <x-theme-sections.login-bg :effect="\App\Support\ThemePreset::sectionStyle('login_bg')" />
            <div class="relative z-10 w-full max-w-sm">
                <div class="mb-6 flex items-center justify-between lg:hidden">
                    <a href="{{ route('home') }}"><x-brand-logo variant="family" size="md" fallback-icon="signal" /></a>
                    <x-theme-toggle />
                </div>

                <div class="mb-6 border-b border-slate-200 pb-5 dark:border-white/10">
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
