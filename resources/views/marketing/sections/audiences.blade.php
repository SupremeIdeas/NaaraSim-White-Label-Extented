{{-- "Who Naara Is For" — audience tabs (BUILD-12). Six tabs auto-advance every
     12s with a brand-gradient progress bar; manual selection overrides and
     restarts the timer; pauses on hover/focus/touch and resumes where it left
     off; keyboard-navigable; honours prefers-reduced-motion (no traveling bar /
     no auto-advance, just a solid active indicator). Section header text is
     CMS-editable (home.audiences); the panel copy is finished/approved. --}}
@php
    // Approved copy, verbatim (§4). image => the SiteContent field that holds the
    // (admin-swappable) image for that panel; falls back to the shipped default.
    $audiences = [
        [
            'title' => 'International Travelers',
            'tagline' => 'Travel Smarter. Stay Connected Everywhere.',
            'body' => 'From weekend getaways to round-the-world adventures, Naara keeps you connected in over 190 countries with instant eSIM activation, affordable data plans, and reliable mobile services before you even land.',
            'best' => ['eSIM', 'Travel Data', 'Regional Plans'],
            'image' => $s['travelers_image'] ?? '/images/audiences/naara-leisure-traveler.webp',
        ],
        [
            'title' => 'Business Professionals',
            'tagline' => 'Power Your Business Across Borders',
            'body' => 'Run your business from anywhere with reliable mobile connectivity, dedicated virtual numbers, secure verification services, and communication tools designed for modern professionals expanding beyond one country.',
            'best' => ['Virtual Numbers', 'eSIM', 'Verification Numbers'],
            'image' => $s['business_image'] ?? '/images/audiences/naara-business-professionals.webp',
        ],
        [
            'title' => 'Digital Entrepreneurs & Freelancers',
            'tagline' => 'Build Without Boundaries',
            'body' => "Whether you're serving international clients, working remotely, or launching digital products, Naara provides the connectivity and communication tools you need to work confidently from anywhere.",
            'best' => ['eSIM', 'Virtual Numbers', 'Verification Numbers'],
            'image' => $s['entrepreneurs_image'] ?? '/images/audiences/naara-software-engineer.webp',
        ],
        [
            'title' => 'Creators, Influencers & Digital Nomads',
            'tagline' => 'Create Without Losing Connection',
            'body' => 'Travel, stream, upload, collaborate, and engage your audience from anywhere. Naara keeps your content flowing with premium mobile data, flexible phone numbers, and dependable connectivity worldwide.',
            'best' => ['eSIM', 'Virtual Numbers'],
            'image' => $s['creators_image'] ?? '/images/audiences/naara-travel-creator.webp',
        ],
        [
            'title' => 'Privacy & Online Security',
            'tagline' => 'Protect Your Identity Online',
            'body' => 'Keep your personal number private while registering for services, managing online accounts, verifying platforms, or communicating professionally using secure virtual and verification numbers.',
            'best' => ['Verification Numbers', 'Virtual Numbers'],
            'image' => $s['privacy_image'] ?? '/images/audiences/naara-secure-professional.webp',
        ],
        [
            'title' => 'Families & Global Communities',
            'tagline' => 'Stay Close Across Every Border',
            'body' => "Whether you're visiting loved ones, supporting family abroad, or sending digital gifts across continents, Naara helps people stay connected through affordable connectivity and instant digital services.",
            'best' => ['eSIM', 'Gift Cards', 'Virtual Numbers'],
            'image' => $s['families_image'] ?? '/images/audiences/naara-staying-connected.webp',
        ],
    ];
@endphp

<section class="px-4 py-20" data-bg="light"
         x-data="{
            active: 0,
            progress: 0,
            duration: 12000,
            paused: false,
            reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            _last: 0,
            _raf: null,
            count: {{ count($audiences) }},
            init() {
                if (this.reduced) return; // no auto-advance / traveling bar
                this._last = performance.now();
                const step = (now) => {
                    if (!this.paused) {
                        this.progress += (now - this._last) / this.duration * 100;
                        if (this.progress >= 100) this.next();
                    }
                    this._last = now;
                    this._raf = requestAnimationFrame(step);
                };
                this._raf = requestAnimationFrame(step);
            },
            select(i) { this.active = i; this.progress = 0; this._last = performance.now(); },
            next() { this.select((this.active + 1) % this.count); },
            prev() { this.select((this.active - 1 + this.count) % this.count); },
            barWidth(i) {
                if (this.reduced) return this.active === i ? 100 : 0;
                return this.active === i ? this.progress : (i < this.active ? 0 : 0);
            },
         }"
         @mouseenter="paused = true" @mouseleave="paused = false"
         @touchstart.passive="paused = true" @touchend.passive="paused = false"
         @focusin="paused = true" @focusout="paused = false">
    <div class="mx-auto w-full max-w-6xl">
        <div class="text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] }}</p>
            <h2 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
            @if (! empty($s['subtext']))
                <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-slate-600 dark:text-slate-300">{{ $s['subtext'] }}</p>
            @endif
        </div>

        {{-- Tab strip — horizontally scrollable on small screens so the six
             labels never cram. Each trigger carries its own progress bar. --}}
        <div class="mt-10 -mx-4 overflow-x-auto px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <div class="mx-auto flex min-w-max gap-2 sm:justify-center" role="tablist" aria-label="Who Naara is for"
                 @keydown.arrow-right.prevent="next()" @keydown.arrow-left.prevent="prev()">
                @foreach ($audiences as $i => $a)
                    <button type="button" role="tab"
                            :id="'aud-tab-{{ $i }}'"
                            :aria-selected="active === {{ $i }} ? 'true' : 'false'"
                            :tabindex="active === {{ $i }} ? 0 : -1"
                            @click="select({{ $i }})"
                            :class="active === {{ $i }}
                                ? 'border-primary/40 bg-white text-slate-900 shadow-sm dark:border-primary/50 dark:bg-white/10 dark:text-white'
                                : 'border-slate-200 bg-white/60 text-slate-500 hover:text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="group relative overflow-hidden rounded-full border px-4 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        {{ $a['title'] }}
                        {{-- Progress bar (brand gradient), width driven by the timer. --}}
                        <span aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 bg-transparent">
                            <span class="block h-full bg-gradient-to-r from-primary via-accent to-primary"
                                  :style="'width:' + barWidth({{ $i }}) + '%'"></span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Panels — only the active one shows; image crossfades on change. --}}
        <div class="relative mt-8">
            @foreach ($audiences as $i => $a)
                <div role="tabpanel" :aria-labelledby="'aud-tab-{{ $i }}'"
                     x-show="active === {{ $i }}" x-cloak
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     @if (! $loop->first) style="display:none;" @endif>
                    <div class="nx-card grid items-center gap-8 !p-6 sm:!p-8 lg:grid-cols-2 lg:!p-10">
                        <div class="order-2 lg:order-1">
                            <p class="font-display text-xl font-bold text-primary dark:text-teal-300">{{ $a['tagline'] }}</p>
                            <h3 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $a['title'] }}</h3>
                            <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $a['body'] }}</p>
                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Best for</span>
                                @foreach ($a['best'] as $tag)
                                    <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:bg-primary/20 dark:text-teal-300">{{ $tag }}</span>
                                @endforeach
                            </div>
                            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary mt-7 !px-7">Get started</a>
                        </div>
                        <div class="order-1 lg:order-2">
                            <img src="{{ $a['image'] }}" alt="{{ $a['title'] }}" width="1280" height="768" loading="lazy"
                                 class="aspect-[5/3] w-full rounded-2xl object-cover shadow-sm">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
