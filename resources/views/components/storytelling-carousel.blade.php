@props([
    'slides' => [],       // [{image, eyebrow, title, body, cta_label?, cta_url?, read_seconds?, modal_gallery?, modal_blocks?}]
    'sectionKey' => null, // unique per instance (nav registry + modal scoping)
    'height' => 'h-72 sm:h-96', // stage height utility
    'tone' => 'auto',     // 'auto' (theme-aware) | 'on-dark' (always-light text, for navy sections)
])

@php
    // Text colour set — 'on-dark' forces light text regardless of theme, for
    // carousels placed on a permanently-dark surface (e.g. the About navy band).
    $onDark = $tone === 'on-dark';
    $cEyebrow = $onDark ? 'text-accent' : 'text-accent-dark dark:text-accent';
    $cTitle = $onDark ? 'text-white' : 'text-slate-900 dark:text-white';
    $cBody = $onDark ? 'text-slate-200' : 'text-slate-600 dark:text-slate-300';
    $cDotOn = $onDark ? 'bg-accent' : 'bg-primary dark:bg-teal-300';
    $cDotOff = $onDark ? 'bg-white/25' : 'bg-slate-300 dark:bg-white/20';
    $cBtn = $onDark
        ? 'border-white/20 bg-white/10 text-white hover:bg-white/15'
        : 'border-slate-200 bg-white text-primary hover:bg-slate-50 dark:border-white/10 dark:bg-[#16233d] dark:text-teal-300';
@endphp

@php
    // Normalise to a clean list and compute the per-slide read time
    //   seconds = max(4, ceil(word_count / 3))   (~180 wpm, 4s floor)
    // admin-overridable via the slide's own read_seconds.
    $slides = collect($slides)->filter(fn ($s) => filled($s['title'] ?? null) || filled($s['image'] ?? null))->values();
    $key = $sectionKey ?: 'story-'.\Illuminate\Support\Str::random(5);
    // Text-only carousels (e.g. About, before imagery is supplied) drop the image
    // column and centre the text, so the section never shows an empty image well.
    $hasImages = $slides->contains(fn ($s) => filled($s['image'] ?? null));

    $first = $slides->first() ?: [];
    $readSeconds = [];
    $jsSlides = [];
    foreach ($slides as $s) {
        $words = str_word_count(strip_tags((string) ($s['body'] ?? '')));
        $readSeconds[] = (int) ($s['read_seconds'] ?? max(4, (int) ceil($words / 3)));
        $jsSlides[] = [
            'eyebrow' => (string) ($s['eyebrow'] ?? ''),
            'title' => (string) ($s['title'] ?? ''),
            'body' => (string) ($s['body'] ?? ''),
            'ctaLabel' => (string) ($s['cta_label'] ?? ''),
            'ctaUrl' => (string) ($s['cta_url'] ?? ''),
            'hasModal' => ! empty($s['modal_gallery']) || ! empty($s['modal_blocks']),
        ];
    }
@endphp

@if ($slides->isNotEmpty())
    <section class="nx-story"
             x-data="storytellingCarousel({ key: @js($key), count: {{ $slides->count() }}, readSeconds: @js($readSeconds), slides: @js($jsSlides) })"
             @touchstart.passive="onTouchStart($event)" @touchend.passive="onTouchEnd($event)"
             aria-roledescription="carousel">

        <div class="grid items-center gap-6 @if ($hasImages) lg:grid-cols-2 @endif">
            {{-- Image layer — stays fixed, crossfades on every change. --}}
            @if ($hasImages)
                <div class="nx-story__stage {{ $height }}">
                    <div class="nx-story__images h-full">
                        @foreach ($slides as $i => $s)
                            @if (! empty($s['image']))
                                <img src="{{ $s['image'] }}" alt="{{ $s['title'] ?? '' }}"
                                     @class(['nx-story__img', 'nx-story__img--lead' => $i === 0])
                                     :class="isActive({{ $i }}) && 'is-active'"
                                     loading="{{ $i === 0 ? 'eager' : 'lazy' }}" draggable="false">
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Text block — single node; JS animates it (slide on gesture, fade on auto). --}}
            <div class="relative @unless ($hasImages) mx-auto max-w-2xl text-center @endunless">
                {{-- The first slide is rendered server-side so crawlers and no-JS
                     visitors see real content; Alpine's x-text takes over on load
                     (same value, no flash) and swaps it as slides change. --}}
                <div x-ref="text" class="nx-tin-fade">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] {{ $cEyebrow }}" x-text="cur.eyebrow" x-show="cur.eyebrow">{{ $first['eyebrow'] ?? '' }}</p>
                    <h2 class="mt-2 font-display text-3xl font-bold leading-tight {{ $cTitle }}" x-text="cur.title">{{ $first['title'] ?? '' }}</h2>
                    <p class="mt-3 text-base leading-relaxed {{ $cBody }}" x-text="cur.body">{{ $first['body'] ?? '' }}</p>
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a @if (! empty($first['cta_url'])) href="{{ $first['cta_url'] }}" @endif x-show="cur.ctaUrl" :href="cur.ctaUrl" wire:navigate
                           class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                            <span x-text="cur.ctaLabel">{{ $first['cta_label'] ?? '' }}</span> <x-icon name="chevron-right" class="h-4 w-4" />
                        </a>
                    </div>
                </div>

                {{-- Dedicated bottom nav — sticky within the section (§3.3). Its
                     visibility is coordinated with the global nav in §6; here it
                     is always shown within its own section. --}}
                <div class="nx-story__nav mt-8 flex items-center gap-4 @unless ($hasImages) justify-center @endunless"
                     x-show="$store.sectionNav.isActive(key)" x-transition.opacity.duration.300ms>
                    <button type="button" @click="toggle()" :aria-label="playing ? 'Pause' : 'Play'"
                            class="flex h-10 w-10 items-center justify-center rounded-full border shadow-sm transition {{ $cBtn }}">
                        <span x-show="playing"><x-icon name="pause" class="h-4 w-4" /></span>
                        <span x-show="!playing" x-cloak><x-icon name="play" class="h-4 w-4" /></span>
                    </button>
                    <div class="flex items-center gap-2" role="tablist" aria-label="Slides">
                        @foreach ($slides as $i => $s)
                            <button type="button" @click="goTo({{ $i }})" wire:key="dot-{{ $key }}-{{ $i }}"
                                    :aria-selected="isActive({{ $i }}).toString()" aria-label="Slide {{ $i + 1 }}"
                                    class="h-2 rounded-full transition-all"
                                    :class="isActive({{ $i }}) ? 'w-6 {{ $cDotOn }}' : 'w-2 {{ $cDotOff }}'"></button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Floating action button — opens the rich modal for the current slide. --}}
        <button type="button" x-show="cur.hasModal" @click="$dispatch('open-modal', { name: modalName() })"
                aria-label="More about this"
                class="absolute bottom-3 right-3 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-dark text-white shadow-lg shadow-primary/30 transition hover:scale-105">
            <x-icon name="message-circle" class="h-5 w-5" />
        </button>

        {{-- One content modal per slide that has deeper content. --}}
        @foreach ($slides as $i => $s)
            @if (! empty($s['modal_gallery']) || ! empty($s['modal_blocks']))
                <x-content-modal :name="$key.'-'.$i" :title="$s['title'] ?? ''"
                                 :gallery="$s['modal_gallery'] ?? []" :blocks="$s['modal_blocks'] ?? []"
                                 :cta-label="$s['cta_label'] ?? null" :cta-url="$s['cta_url'] ?? null" />
            @endif
        @endforeach
    </section>
@endif
