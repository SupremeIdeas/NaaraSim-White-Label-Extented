{{-- Per-theme custom landing page — "neon-vertex" (Theme visual rebuild,
     owner request 2026-09-07). Structurally mimics a SaaS-dashboard-hero
     reference (bold display headline with a gradient last line, a big
     blob-gradient visual with a floating stat card, three feature cards) —
     recoloured entirely in the neon-vertex ultraviolet/hot-pink persona and
     rewritten for NaaraSim's eSIM/numbers product. Every text/image field
     below comes from $content, resolved+whitelisted by
     ThemePreset::landingContent() against LandingHeroLibrary's schema for
     this style — an admin edits it from Theme → Landing page, nothing here
     is hardcoded except the surrounding structure and the 3 feature cards
     (not yet part of the editable schema — see LandingHeroLibrary to add
     more fields later, the admin editor grows automatically). Fully
     responsive: single column on mobile, side-by-side from lg. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'xl') {
        'none' => 'rounded-none', 'md' => 'rounded-2xl', 'full' => 'rounded-full', default => 'rounded-[2.5rem]',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="pointer-events-none absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-gradient-to-br from-primary/30 via-accent/25 to-transparent blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -left-24 top-1/2 h-80 w-80 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-4 pb-16 pt-14 sm:pb-24 sm:pt-20">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            <div class="{{ $imageOnLeft ? 'lg:order-2' : '' }} {{ $imageCentered ? 'text-center lg:col-span-2' : 'text-center lg:text-left' }}">
                <span class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-semibold text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-300">
                    <x-icon name="zap" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
                </span>

                @php
                    $words = preg_split('/\s+/', trim($content['headline']));
                    $last = array_pop($words) ?: '';
                    $lead = implode(' ', $words);
                @endphp
                <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl lg:mx-0 dark:text-white">
                    {{ $lead }} <span class="bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent">{{ $last }}</span>
                </h1>
                <p class="mx-auto mt-5 max-w-lg text-lg leading-relaxed text-slate-600 lg:mx-0 dark:text-slate-300">
                    {{ $content['description'] }}
                </p>

                <div class="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row lg:mx-0">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-primary to-accent px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-accent/30 transition hover:opacity-90">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5">
                        <x-icon name="play" class="h-4 w-4" /> How it works
                    </a>
                </div>
            </div>

            <div class="{{ $imageOnLeft ? 'lg:order-1' : '' }} {{ $imageCentered ? 'lg:col-span-2' : '' }} relative">
                <div class="relative mx-auto max-w-md">
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} object-cover shadow-2xl">
                    @else
                        <div class="aspect-square w-full {{ $radiusClass }} bg-gradient-to-br from-primary via-accent to-primary-dark shadow-2xl"></div>
                    @endif

                    {{-- Floating stat card, mirroring the reference's "Projects Launched" overlay. --}}
                    <div class="absolute -bottom-6 -left-4 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-xl dark:border-white/10 dark:bg-[#12172a] sm:-left-8">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                            <x-icon name="globe" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="font-display text-lg font-bold leading-none text-slate-900 dark:text-white">{{ $content['stat_value'] }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ $content['stat_label'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Three feature cards, structural echo of the reference's card row. --}}
        <div class="mt-20 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'sim', 'title' => 'eSIM in seconds', 'body' => 'Scan a QR and you\'re connected — no store visit, no waiting.'],
                ['icon' => 'phone', 'title' => 'A real second number', 'body' => 'Voice + SMS that follows you, wherever you land.'],
                ['icon' => 'wallet', 'title' => 'One wallet, one price', 'body' => 'Top up once — data and numbers both draw from it.'],
            ] as $card)
                <div class="rounded-3xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-[#12172a]">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white">
                        <x-icon :name="$card['icon']" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
