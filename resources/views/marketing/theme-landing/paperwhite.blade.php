{{-- Per-theme custom landing page — "paperwhite" (Theme visual rebuild,
     owner request 2026-09-07). Persona: "Ultra-light, high-whitespace,
     ink-black type on paper — minimal, editorial, zero noise." The
     deliberate OPPOSITE of every card-grid/gradient-blob hero on the
     platform: no floating stat card, no filled button, no dominant hero
     visual. A single centred editorial reading column — a magazine cover
     page, not a SaaS dashboard hero — then a second section reading like a
     masthead index of three quiet columns, DIVIDED by hairlines rather than
     boxed into cards, so the "no shared skeleton" rule holds against
     neon-vertex's 3-card row and midnight-signal's 4-icon grid: same three
     ideas, a genuinely different (divider, not container) composition.
     Every text/image field below comes from $content, resolved+whitelisted
     by ThemePreset::landingContent() against LandingHeroLibrary's schema
     for this style. `font-serif` (Tailwind's default Georgia-led stack) is
     used deliberately here and nowhere else on the platform, to make this
     theme's "editorial" persona a genuine typographic difference, not just
     a recolour — every other theme uses the sans `font-display` face. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'none') {
        'md' => 'rounded-2xl', 'xl' => 'rounded-[2.5rem]', 'full' => 'rounded-full', default => 'rounded-none',
    };
    $justify = match ($content['image_position'] ?? 'center') {
        'left' => 'justify-start text-left', 'right' => 'justify-end text-right', default => 'justify-center text-center',
    };
@endphp

<section class="bg-[#F8F9FA] dark:bg-navy">
    <div class="mx-auto max-w-2xl px-6 pb-16 pt-24 text-center sm:pb-20 sm:pt-32">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400 dark:text-slate-500">
            {{ $content['eyebrow'] }}
        </p>

        <h1 class="mx-auto mt-6 max-w-xl font-serif text-4xl italic leading-[1.15] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>

        <p class="mx-auto mt-6 max-w-md text-base leading-relaxed text-slate-500 dark:text-slate-400">
            {{ $content['description'] }}
        </p>

        <div class="mt-9 flex justify-center">
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
               class="group inline-flex items-center gap-1.5 border-b border-slate-900 pb-1 text-sm font-semibold text-slate-900 transition hover:gap-2.5 dark:border-white dark:text-white">
                {{ $content['cta_label'] }}
                <x-icon name="chevron-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
            </a>
        </div>
    </div>

    {{-- The feature image sits small and unobtrusive below the reading
         column, framed only by a hairline — never a dominant hero visual —
         paired with the stat as a quiet photo caption, not a floating card. --}}
    <div class="mx-auto max-w-2xl px-6 pb-24">
        <div class="flex flex-col items-center gap-4 border-t border-slate-200 pt-10 dark:border-white/10 {{ $justify }}">
            @if ($content['image'])
                <img src="{{ $content['image'] }}" alt="" class="h-40 w-64 {{ $radiusClass }} border border-slate-200 object-cover dark:border-white/10">
            @endif
            <p class="text-xs leading-relaxed text-slate-400 dark:text-slate-500">
                <span class="font-serif text-base not-italic text-slate-700 dark:text-slate-200">{{ $content['stat_value'] }}</span>
                &nbsp;— {{ $content['stat_label'] }}
            </p>
        </div>
    </div>
</section>

{{-- Three quiet columns, divided by hairlines rather than boxed into
     cards — the same three product ideas as the card/icon-grid version
     other themes use, composed as a masthead index instead. --}}
<section class="border-t border-slate-200 bg-[#F8F9FA] dark:border-white/10 dark:bg-navy">
    <div class="mx-auto grid max-w-4xl divide-y divide-slate-200 px-6 py-4 dark:divide-white/10 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        @foreach ([
            ['title' => 'eSIM in seconds', 'body' => 'Scan a QR and you\'re connected — no store visit, no waiting.'],
            ['title' => 'A real second number', 'body' => 'Voice and SMS that follow you, wherever you land.'],
            ['title' => 'One wallet, one price', 'body' => 'Top up once — data and numbers both draw from it.'],
        ] as $col)
            <div class="px-6 py-10 text-center sm:px-8">
                <h3 class="font-serif text-lg italic text-slate-900 dark:text-white">{{ $col['title'] }}</h3>
                <p class="mx-auto mt-2 max-w-[16rem] text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $col['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>
