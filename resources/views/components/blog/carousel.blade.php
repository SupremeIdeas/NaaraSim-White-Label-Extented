{{-- Post carousel (CSS scroll-snap + Alpine arrows/dots/keyboard). Server-rendered so it is crawlable and works without JS (scrolls natively).
     Props: items (array from RelatedPosts), label, source (analytics tag: inline|popup|menu) --}}
@props(['items', 'label' => 'More articles', 'source' => 'inline'])
@if (count($items) > 0)
<div x-data="{ i: 0, n: {{ count($items) }},
        go(k) { const t = this.$refs.track; const c = t.children[Math.max(0, Math.min(this.n - 1, k))]; if (c) t.scrollTo({ left: c.offsetLeft - t.offsetLeft, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); },
        sync() { const t = this.$refs.track; let best = 0, d = 1e9; [...t.children].forEach((c, k) => { const x = Math.abs(c.offsetLeft - t.offsetLeft - t.scrollLeft); if (x < d) { d = x; best = k; } }); this.i = best; } }"
     role="region" aria-roledescription="carousel" aria-label="{{ $label }}" class="relative" @keydown.arrow-right.prevent="go(i + 1)" @keydown.arrow-left.prevent="go(i - 1)">
    <ul x-ref="track" @scroll.passive.debounce.80ms="sync()" tabindex="0" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden focus-visible:outline focus-visible:outline-2">
        @foreach ($items as $k => $it)
            <li role="group" aria-roledescription="slide" aria-label="{{ $k + 1 }} of {{ count($items) }}" class="w-[78%] shrink-0 snap-start sm:w-[46%] lg:w-[31%]">
                <a href="{{ $it['url'] }}" @click="$dispatch('blog-card-click', { source: '{{ $source }}' })" class="group block h-full overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] transition hover:shadow-lg focus-visible:outline focus-visible:outline-2">
                    <div class="aspect-[16/9] overflow-hidden bg-[rgb(var(--nx-surface-3))]">
                        @if ($it['cover'])
                            <img src="{{ $it['cover'] }}" alt="" loading="lazy" decoding="async" width="640" height="360" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#0D1B2A] to-[#0A6E6E] text-3xl font-bold text-white/80" aria-hidden="true">{{ mb_strtoupper(mb_substr($it['category'] ?: $it['title'], 0, 1)) }}</div>
                        @endif
                    </div>
                    <div class="p-3.5">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-teal-ink))]">{{ $it['category'] }}</span>
                        <h3 class="mt-1 line-clamp-2 text-sm font-semibold leading-snug text-[rgb(var(--nx-text))]">{{ $it['title'] }}</h3>
                        @if ($it['excerpt'] !== '')<p class="mt-1.5 line-clamp-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $it['excerpt'] }}</p>@endif
                        <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $it['minutes'] }} min read</p>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="mt-2 hidden items-center justify-between sm:flex">
        <div class="flex gap-1.5" aria-hidden="true">
            @foreach ($items as $k => $it)<span class="h-1.5 rounded-full transition-all" :class="i === {{ $k }} ? 'w-5 bg-[rgb(var(--nx-teal-ink))]' : 'w-1.5 bg-[rgb(var(--nx-line-strong))]'"></span>@endforeach
        </div>
        <div class="flex gap-1.5">
            <button type="button" @click="go(i - 1)" :disabled="i === 0" class="rounded-full border border-[rgb(var(--nx-line))] p-2 text-[rgb(var(--nx-text))] disabled:opacity-40" aria-label="Previous"><x-icon name="chevron-right" class="h-4 w-4 rotate-180" /></button>
            <button type="button" @click="go(i + 1)" :disabled="i >= n - 1" class="rounded-full border border-[rgb(var(--nx-line))] p-2 text-[rgb(var(--nx-text))] disabled:opacity-40" aria-label="Next"><x-icon name="chevron-right" class="h-4 w-4" /></button>
        </div>
    </div>
</div>
@endif
