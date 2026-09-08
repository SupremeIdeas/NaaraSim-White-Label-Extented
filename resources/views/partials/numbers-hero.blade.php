@php
    $hero = \App\Support\NumbersHeroContent::current();
    $images = $hero['images'];
    $peek = count($images) >= 3; // a peek carousel needs 3+ to look right
@endphp
@if (! empty($images))
    @if ($peek)
        {{--
            Responsive hero (mirrors the eSIM hero): PHONES keep today's single-slide
            banner; TABLET + DESKTOP get the Apple-style 3-up peek carousel. One
            Alpine instance drives both markups. NumbersHeroContent is unchanged.
        --}}
        <div x-data="numbersHero({{ count($images) }})"
             @mouseenter="pause()" @mouseleave="resume()"
             @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
             tabindex="0" @keydown.arrow-left.prevent="prev()" @keydown.arrow-right.prevent="next()"
             role="region" aria-label="Numbers highlights" aria-roledescription="carousel">

            {{-- ▸ Phones (<md): the original single-slide banner, unchanged. --}}
            <div class="nx-imghero mb-6 aspect-[2/1] w-full overflow-hidden rounded-3xl border border-slate-200/70 shadow-sm dark:border-white/10 md:hidden">
                @foreach ($images as $i => $src)
                    <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'">
                        <img src="{{ $src }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="480">
                    </div>
                @endforeach
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 p-4">
                    <div class="min-w-0 max-w-lg">
                        <h1 class="line-clamp-2 text-lg font-bold leading-tight text-white">{{ $hero['title'] }}</h1>
                        <p class="mt-1 line-clamp-1 text-xs text-white/80">{{ $hero['description'] }}</p>
                    </div>
                </div>
                <div class="absolute right-4 top-4 flex gap-1.5">
                    @foreach ($images as $i => $src)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            </div>

            {{-- ▸ Tablet + desktop (md+): the 3-up peek carousel. --}}
            <div class="nx-imghero nx-imghero--peek relative mb-6 hidden h-80 w-full md:block lg:h-[22rem]">
                <div class="nx-imghero__track" :style="`transform: translateX(${8 - active * 84}%)`">
                    @foreach ($images as $i => $src)
                        <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'"
                             @click="go({{ $i }})" role="button" tabindex="-1"
                             aria-label="Show highlight {{ $i + 1 }}">
                            <div class="relative h-full w-full border border-slate-200/70 bg-slate-100 shadow-sm dark:border-white/10 dark:bg-white/5">
                                <img src="{{ $src }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async"
                                     width="1280" height="480" class="h-full w-full object-cover">
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="pointer-events-none absolute inset-y-0 left-1/2 w-[84%] -translate-x-1/2 overflow-hidden rounded-[var(--radius-card,1.5rem)]">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6">
                        <div class="min-w-0 max-w-lg">
                            <h1 class="line-clamp-2 text-3xl font-bold leading-tight text-white">{{ $hero['title'] }}</h1>
                            <p class="mt-1 line-clamp-1 text-base text-white/80">{{ $hero['description'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="absolute right-[9%] top-4 flex gap-1.5">
                    @foreach ($images as $i => $src)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        {{-- Fewer than 3 images: keep today's single-slide crossfade banner. --}}
        <div class="nx-imghero mb-6 aspect-[2/1] w-full overflow-hidden rounded-3xl border border-slate-200/70 shadow-sm dark:border-white/10"
             x-data="numbersHero({{ count($images) }})"
             @mouseenter="pause()" @mouseleave="resume()"
             @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
             role="region" aria-label="Numbers highlights">
            @foreach ($images as $i => $src)
                <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'">
                    <img src="{{ $src }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="480">
                </div>
            @endforeach

            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>

            <div class="absolute inset-x-0 bottom-0 p-4 sm:p-6">
                <div class="min-w-0 max-w-lg">
                    <h1 class="line-clamp-2 text-lg font-bold leading-tight text-white sm:text-2xl">{{ $hero['title'] }}</h1>
                    <p class="mt-1 line-clamp-1 text-xs text-white/80 sm:text-sm">{{ $hero['description'] }}</p>
                </div>
            </div>

            @if (count($images) > 1)
                <div class="absolute right-4 top-4 flex gap-1.5">
                    @foreach ($images as $i => $src)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @once
        <script>
            function numbersHero(count) {
                return {
                    active: 0, timer: null, sx: 0,
                    init() { this.start(); },
                    start() { if (count > 1) this.timer = setInterval(() => this.next(), 5000); },
                    pause() { clearInterval(this.timer); },
                    resume() { this.pause(); this.start(); },
                    next() { this.active = (this.active + 1) % count; },
                    prev() { this.active = (this.active - 1 + count) % count; this.resume(); },
                    go(i) { this.active = i; this.resume(); },
                    touchStart(e) { this.sx = e.changedTouches[0].screenX; },
                    touchEnd(e) {
                        const dx = e.changedTouches[0].screenX - this.sx;
                        if (Math.abs(dx) < 40) return;
                        this.active = dx < 0 ? (this.active + 1) % count : (this.active - 1 + count) % count;
                        this.resume();
                    },
                };
            }
        </script>
    @endonce
@endif
