{{-- Per-theme custom landing page — "waitlisty-soft" ("Horizon", Theme
     visual rebuild). Structurally different from neon-vertex's side-by-side
     "headline left / image right + stat card + 3-col grid" hero: this is a
     CENTRED, stacked "friendly waitlist" composition traced to the
     approachable-consumer-app / waitlist-landing genre (Dribbble/Behance-
     calibre pattern: centred pill eyebrow → centred bold headline → centred
     description+CTAs → one big circular hero photo below, orbited by
     floating pill badges → a STAGGERED row of feature cards with alternating
     vertical offset, never a flat aligned grid). Every text/image field
     below comes from $content, resolved+whitelisted by
     ThemePreset::landingContent() against LandingHeroLibrary's schema for
     this style. Fully responsive: everything collapses to a single centred
     column on mobile. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'full') {
        'none' => 'rounded-none', 'md' => 'rounded-2xl', 'xl' => 'rounded-[2.5rem]', default => 'rounded-full',
    };
    $imageAlign = match ($content['image_position'] ?? 'right') {
        'left' => 'mr-auto', 'center' => 'mx-auto', default => 'ml-auto',
    };
@endphp
<section class="relative overflow-hidden bg-[#FBF7FF] dark:bg-navy">
    <span class="pointer-events-none absolute -left-20 top-10 h-72 w-72 rounded-[40%_60%_65%_35%/45%_40%_60%_55%] bg-primary/15 blur-3xl" aria-hidden="true"></span>
    <span class="pointer-events-none absolute -right-16 bottom-0 h-64 w-64 rounded-full bg-accent/15 blur-3xl" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-3xl px-4 pb-6 pt-16 text-center sm:pt-24">
        <span class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-xs font-semibold text-primary dark:bg-primary/20 dark:text-violet-200">
            <x-icon name="sparkles" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>

        <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-bold leading-[1.15] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-lg text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['description'] }}
        </p>

        <div class="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-primary to-accent px-7 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:opacity-90">
                {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
            <a href="{{ route('how-it-works') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/20 px-7 py-3.5 text-sm font-semibold text-slate-700 transition hover:bg-primary/5 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5">
                <x-icon name="play" class="h-4 w-4" /> See how it works
            </a>
        </div>
    </div>

    {{-- Big circular hero photo with floating pill badges — the piece that
         replaces neon-vertex's side-column image entirely. --}}
    <div class="relative mx-auto mt-12 max-w-lg px-4 pb-8 sm:mt-16">
        <div class="relative w-64 sm:w-80 {{ $imageAlign }}">
            @if ($content['image'])
                <img src="{{ $content['image'] }}" alt="" class="aspect-square w-full {{ $radiusClass }} object-cover shadow-2xl shadow-primary/20">
            @else
                <div class="aspect-square w-full {{ $radiusClass }} bg-gradient-to-br from-primary via-accent to-primary-dark shadow-2xl"></div>
            @endif

            <div class="absolute -left-6 top-6 flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-xl dark:bg-[#1c1329] sm:-left-10">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon name="globe" class="h-4 w-4" />
                </span>
                <div>
                    <p class="font-display text-base font-bold leading-none text-slate-900 dark:text-white">{{ $content['stat_value'] }}</p>
                    <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">{{ $content['stat_label'] }}</p>
                </div>
            </div>
            <div class="absolute -right-4 bottom-4 flex h-14 w-14 items-center justify-center rounded-full bg-accent text-white shadow-xl sm:-right-8">
                <x-icon name="heart" class="h-6 w-6" />
            </div>
        </div>
    </div>

    {{-- Staggered feature cards — alternating vertical offset instead of a
         flat aligned 3-col grid, matching the "hand-placed sticker" feel of
         the reference genre. --}}
    <div class="relative mx-auto grid max-w-5xl gap-5 px-4 pb-20 sm:grid-cols-3 sm:gap-6">
        @foreach ([
            ['icon' => 'sim', 'title' => 'Data, instantly', 'body' => 'Scan a QR and you\'re online — no store, no queue, no drama.', 'shift' => ''],
            ['icon' => 'phone', 'title' => 'A number that\'s yours', 'body' => 'Real voice and SMS that travels with you, wherever you go.', 'shift' => 'sm:mt-8'],
            ['icon' => 'wallet', 'title' => 'One friendly wallet', 'body' => 'Top up once — data and numbers both draw from the same balance.', 'shift' => ''],
        ] as $card)
            <div class="{{ $card['shift'] }} rounded-[2rem] bg-white p-6 shadow-[0_18px_40px_-24px_rgba(109,63,160,0.4)] transition hover:-translate-y-1 dark:bg-[#1c1329]">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white">
                    <x-icon :name="$card['icon']" class="h-5 w-5" />
                </span>
                <h3 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>
