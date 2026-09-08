@props(['placement' => 'dashboard_home'])

@php
    $banners = \App\Support\Banners::for($placement);
    // Aspect per zone, so any correctly-sized upload fits every device.
    $aspect = match ($placement) {
        // 3:1 on every breakpoint so a 1200×400 banner shows in FULL on mobile
        // (no side crop) — matches the shipped artwork's aspect exactly.
        'dashboard_home' => 'aspect-[3/1]',
        'account' => 'aspect-[2/1]',
        default => 'aspect-[2/1]',
    };
@endphp

@if ($banners->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'nx-banner-zone']) }}
         @if ($banners->count() > 1)
             x-data="{
                 i: 0, n: {{ $banners->count() }}, timer: null, sx: 0,
                 start() {
                     if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                     this.timer = setInterval(() => this.i = (this.i + 1) % this.n, 6000);
                 },
                 stop() { clearInterval(this.timer); },
                 go(k) { this.stop(); this.i = (k + this.n) % this.n; this.start(); },
             }"
             x-init="start()" @mouseenter="stop()" @mouseleave="start()"
             @touchstart.passive="sx = $event.touches[0].clientX"
             @touchend.passive="const dx = $event.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) go(dx < 0 ? i + 1 : i - 1)"
             role="region" aria-roledescription="carousel" aria-label="Offers and announcements"
         @else
             x-data="{ i: 0 }"
         @endif>
        <div class="relative {{ $aspect }} w-full overflow-hidden rounded-3xl border border-slate-200/70 shadow-sm dark:border-white/10">
            @foreach ($banners as $k => $banner)
                <div wire:key="bnr-{{ $placement }}-{{ $banner->id }}"
                     x-show="i === {{ $k }}"
                     x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute inset-0" @if ($k > 0) x-cloak @endif>
                    @php($external = $banner->isExternalLink())
                    @if ($banner->link_url)
                        <a href="{{ $banner->link_url }}" @if ($external) target="_blank" rel="noopener" @endif
                           class="block h-full w-full" aria-label="{{ $banner->title }}">
                    @endif
                        @if ($banner->hasVideo())
                            {{-- Motion banner: muted-looping video with the artwork as
                                 poster/fallback. Reduced-motion viewers keep the poster. --}}
                            <video class="h-full w-full object-cover" autoplay muted loop playsinline preload="metadata"
                                   poster="{{ $banner->image_url }}" aria-label="{{ $banner->title }}"
                                   x-init="if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { $el.removeAttribute('autoplay'); $el.pause(); }">
                                <source src="{{ $banner->video_url }}" type="video/{{ \Illuminate\Support\Str::endsWith($banner->video_url, '.webm') ? 'webm' : 'mp4' }}">
                            </video>
                        @else
                            <picture>
                                @if ($banner->image_url_mobile)
                                    <source media="(max-width: 640px)" srcset="{{ $banner->image_url_mobile }}">
                                @endif
                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}"
                                     loading="lazy" class="h-full w-full object-cover">
                            </picture>
                        @endif
                    @if ($banner->link_url)
                        </a>
                    @endif

                    {{-- Coupon chip: tap to copy, paste at checkout. --}}
                    @if ($banner->coupon && $banner->coupon->isRedeemable())
                        <div x-data="{ copied: false }" class="absolute bottom-3 left-3">
                            <button type="button"
                                    @click.stop.prevent="navigator.clipboard.writeText(@js($banner->coupon->code)); copied = true; setTimeout(() => copied = false, 1800)"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-navy/80 px-3 py-1.5 text-xs font-semibold text-white shadow backdrop-blur transition hover:bg-navy"
                                    aria-label="Copy coupon code {{ $banner->coupon->code }}">
                                <x-icon name="gift" class="h-3.5 w-3.5 text-accent" x-show="!copied" />
                                <x-icon name="check" class="h-3.5 w-3.5 text-green-400" x-show="copied" x-cloak />
                                <span class="font-mono tracking-wider" x-show="!copied">{{ $banner->coupon->code }}</span>
                                <span x-show="copied" x-cloak>Copied — use at checkout</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Dots + arrows (only when rotating) --}}
            @if ($banners->count() > 1)
                <div class="absolute bottom-3 right-3 flex items-center gap-1.5">
                    @foreach ($banners as $k => $banner)
                        <button type="button" @click="go({{ $k }})"
                                :class="i === {{ $k }} ? 'w-5 bg-white' : 'w-2 bg-white/50 hover:bg-white/80'"
                                class="h-2 rounded-full shadow transition-all" aria-label="Show banner {{ $k + 1 }}"></button>
                    @endforeach
                </div>
                <button type="button" @click="go(i - 1)" aria-label="Previous banner"
                        class="absolute left-2 top-1/2 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/25 text-white backdrop-blur transition hover:bg-black/45 sm:flex">
                    <x-icon name="chevron-right" class="h-4 w-4 rotate-180" />
                </button>
                <button type="button" @click="go(i + 1)" aria-label="Next banner"
                        class="absolute right-2 top-1/2 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/25 text-white backdrop-blur transition hover:bg-black/45 sm:flex">
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </button>
            @endif
        </div>
    </div>
@endif
