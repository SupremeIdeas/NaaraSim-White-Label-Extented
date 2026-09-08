{{-- Per-theme custom How It Works page — "midnight-signal" (Theme visual
     rebuild, owner request 2026-09-07). REWRITTEN (owner feedback: stop
     recolouring the same vertical numbered card rail on every theme).
     Structure: an editorial two-column layout — plain step text on the
     left, a rail of STACKED FLOATING PILLS on the right (Stryds fitness
     app's "25 min Focus / 955 Calories / 18,022 Steps" pill stack), each
     pill carrying a small icon-avatar + bold value + label instead of a
     numbered card. Compatibility keeps neon-vertex's circular-button idea
     but restyled dark/cyan, proving the pattern is reusable across
     personas without the two pages looking identical. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute left-1/2 top-0 h-[24rem] w-[24rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-primary/20 blur-3xl"></span>
    </div>
    <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:pb-16 sm:pt-24">
        <h1 class="mx-auto max-w-xl font-display text-4xl font-bold leading-tight text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="bg-navy px-4 pb-20">
    <div class="mx-auto grid max-w-4xl items-start gap-10 lg:grid-cols-2">
        <div class="space-y-8">
            @foreach ([
                ['title' => 'We scan the route', 'body' => 'Every carrier along your destination is checked for live coverage and quality before you ever see a plan.'],
                ['title' => 'You lock in a rate', 'body' => 'Your wallet pays once, at the price shown — no roaming surcharges added later.'],
                ['title' => 'The signal room activates you', 'body' => 'Your eSIM QR is issued and monitored from the moment it\'s generated.'],
                ['title' => 'You land already tracked', 'body' => 'If a network dips mid-trip, the signal room flags it before you notice a dropped bar.'],
            ] as $step)
                <div>
                    <h3 class="font-display text-lg font-bold text-white">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-300">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Stacked floating pill rail — one per step, alternating colours like the fitness-app reference. --}}
        <div class="flex flex-col gap-3">
            @foreach ([
                ['icon' => 'signal', 'value' => '190+', 'label' => 'Networks tracked', 'from' => 'from-primary', 'to' => 'to-primary-dark'],
                ['icon' => 'wallet', 'value' => '$0', 'label' => 'Surprise fees', 'from' => 'from-accent', 'to' => 'to-accent-dark'],
                ['icon' => 'sim', 'value' => '&lt; 2 min', 'label' => 'To activation', 'from' => 'from-primary-dark', 'to' => 'to-primary'],
                ['icon' => 'clock', 'value' => '24/7', 'label' => 'Signal monitoring', 'from' => 'from-accent-dark', 'to' => 'to-accent'],
            ] as $pill)
                <div class="flex items-center gap-4 rounded-full bg-gradient-to-r {{ $pill['from'] }} {{ $pill['to'] }} py-3 pl-3 pr-6 shadow-lg">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
                        <x-icon :name="$pill['icon']" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-lg font-bold leading-none text-white">{!! $pill['value'] !!}</p>
                        <p class="mt-1 text-xs text-white/85">{{ $pill['label'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Compatibility — circular quick-link buttons, restyled dark/cyan. Pulled
     up over the navy steps section above with a 30px rounded-top "sheet"
     edge instead of a flat seam. --}}
<section class="relative -mt-8 rounded-t-[30px] bg-[#F8F9FA] px-4 py-20 text-center dark:bg-[#0c1220]">
    <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white">Check your phone supports eSIM first</h2>
    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">Most phones from the last 4 years do — we check automatically before you pay, so there's never a wasted purchase.</p>

    <div class="mt-8 flex justify-center gap-8">
        @foreach ([
            ['icon' => 'smartphone', 'label' => 'iPhone'],
            ['icon' => 'smartphone', 'label' => 'Android'],
            ['icon' => 'badge-check', 'label' => 'Check mine'],
        ] as $btn)
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="group flex flex-col items-center gap-2">
                <span class="flex h-16 w-16 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary shadow-lg transition group-hover:scale-105 dark:bg-primary/20">
                    <x-icon :name="$btn['icon']" class="h-6 w-6" />
                </span>
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $btn['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>

@include('marketing._reused-sections')
