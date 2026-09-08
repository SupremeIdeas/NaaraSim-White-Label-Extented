{{-- Per-theme custom How It Works page — "waitlisty-soft" ("Horizon",
     Theme visual rebuild). Structurally distinct from midnight-signal's
     two-column text+floating-pill-stack layout: a single-column WINDING
     STORY TIMELINE — a soft dashed centre line with blob-shaped numbered
     stops, each step's card alternating left/right of the line like a
     friendly chat thread, rather than two side-by-side blocks. Same 4
     functional steps every theme uses ("pick destination" → "choose a plan,
     pay once" → "scan the QR" → "land already connected"), reworded warmly
     for this persona. --}}
<section class="relative overflow-hidden bg-[#FBF7FF] dark:bg-navy">
    <span class="pointer-events-none absolute left-1/2 top-0 h-72 w-72 -translate-x-1/2 -translate-y-1/3 rounded-full bg-accent/15 blur-3xl" aria-hidden="true"></span>
    <div class="relative mx-auto max-w-2xl px-4 pb-12 pt-16 text-center sm:pb-16 sm:pt-24">
        <h1 class="mx-auto max-w-xl font-display text-4xl font-bold leading-tight text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="relative bg-[#FBF7FF] px-4 pb-20 dark:bg-navy">
    <div class="relative mx-auto max-w-md">
        {{-- Soft dashed centre line, running the full height of the timeline. --}}
        <div class="pointer-events-none absolute inset-y-0 left-1/2 w-px -translate-x-1/2 border-l-2 border-dashed border-primary/25" aria-hidden="true"></div>

        <div class="relative space-y-10">
            @foreach ([
                ['title' => 'Pick where you\'re headed', 'body' => 'Tell us your destination — we\'ll show every plan that covers it, in plain language.'],
                ['title' => 'Choose a plan, pay once', 'body' => 'One tap, one price from your wallet. No hidden fees waiting to surprise you later.'],
                ['title' => 'Scan the little QR code', 'body' => 'Your eSIM shows up instantly. Point your camera at it and you\'re basically done.'],
                ['title' => 'Land already connected', 'body' => 'Maps, messages, calls to home — all working the second wheels touch down.'],
            ] as $i => $step)
                <div class="relative flex items-start gap-4 {{ $i % 2 === 1 ? 'sm:flex-row-reverse sm:text-right' : '' }}">
                    <span class="relative z-10 flex h-11 w-11 shrink-0 items-center justify-center rounded-[42%_58%_60%_40%/50%_45%_55%_50%] bg-gradient-to-br from-primary to-accent text-sm font-bold text-white shadow-lg shadow-primary/30">
                        {{ $i + 1 }}
                    </span>
                    <div class="flex-1 rounded-[1.75rem] bg-white p-5 shadow-[0_16px_34px_-24px_rgba(109,63,160,0.45)] dark:bg-[#1c1329]">
                        <h3 class="font-display text-base font-bold text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Compatibility — soft pastel circular quick-links, pulled up over the
     steps section above with the shared rounded-top "sheet" overlap. --}}
<section class="relative -mt-8 rounded-t-[2.5rem] bg-white px-4 py-20 text-center dark:bg-[#12081f]">
    <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white">Quick check: does your phone support eSIM?</h2>
    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">Most phones from the last few years do. We check automatically before you pay, so there's never a wasted purchase.</p>

    <div class="mt-8 flex justify-center gap-8">
        @foreach ([
            ['icon' => 'smartphone', 'label' => 'iPhone'],
            ['icon' => 'smartphone', 'label' => 'Android'],
            ['icon' => 'badge-check', 'label' => 'Check mine'],
        ] as $btn)
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="group flex flex-col items-center gap-2">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary shadow-lg transition group-hover:scale-105 dark:bg-primary/20">
                    <x-icon :name="$btn['icon']" class="h-6 w-6" />
                </span>
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $btn['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>

@include('marketing._reused-sections')
