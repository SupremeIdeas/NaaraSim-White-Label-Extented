@php $hero = \App\Support\TopupHeroContent::current(); @endphp
@if ($hero['enabled'])
<section class="ns-tp__heroCard" data-tour="topup-hero" aria-label="Top-up" wire:ignore.self x-data="{ bg: '', busy: null, cur: '', pal: null, accent: '',
        mode() { return document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
        paintText(el, q) { el.style.setProperty('--h-base', q.base); el.style.setProperty('--h-ink', q.ink) },
        paintAurora(node, q) { node.style.setProperty('--a-base', q.base); node.style.setProperty('--a-a1', q.a1); node.style.setProperty('--a-a2', q.a2) },
        paint(el, q) { this.paintText(el, q); this.paintAurora(el.querySelector('.ns-tp__aurora:not(.ns-tp__reveal)') || el, q) },
        apply(c, k, p) { if (! c || ! p || c === this.cur) return; this.cur = c; this.pal = p; const el = this.$el; const q = p[this.mode()];
            el.style.setProperty('--tp-screen', c); setTimeout(() => el.style.setProperty('--tp-sink', k), 300);
            const pair = () => { el.style.setProperty('--tp-acc', c); el.style.setProperty('--tp-acc-ink', k); };
            if (! this.bg || window.matchMedia('(prefers-reduced-motion: reduce)').matches || ! el.animate) { this.bg = c; this.paint(el, q); pair(); return; }
            if (this.busy) { this.busy.cancel(); this.busy.__el?.remove(); }
            const ph = el.querySelector('.tls__phone'); const R = el.getBoundingClientRect(); const P = ph ? ph.getBoundingClientRect() : R;
            const x = P.left + P.width / 2 - R.left, y = P.top + P.height / 2 - R.top; const reach = Math.hypot(Math.max(x, R.width - x), Math.max(y, R.height - y)) + 8;
            const layer = document.createElement('span'); layer.className = 'ns-tp__aurora ns-tp__reveal'; this.paintAurora(layer, q); el.prepend(layer);
            const anim = layer.animate([{ clipPath: 'circle(0px at ' + x + 'px ' + y + 'px)' }, { clipPath: 'circle(' + reach + 'px at ' + x + 'px ' + y + 'px)' }], { duration: 1200, easing: 'cubic-bezier(.65,0,.2,1)', fill: 'forwards' });
            anim.__el = layer; this.busy = anim; setTimeout(() => { pair(); this.paintText(el, q) }, 460);
            anim.finished.then(() => { this.bg = c; this.paint(el, q); layer.remove(); if (this.busy === anim) this.busy = null; }).catch(() => {}); },
        init() { new MutationObserver(() => { if (this.pal && ! this.busy) this.paint(this.$el, this.pal[this.mode()]) }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] }) } }"
     x-on:tp-tint.window="apply($event.detail.c, $event.detail.k, $event.detail.p)">
    <span class="ns-tp__aurora" aria-hidden="true"></span>
    <div class="ns-tp__heroTxt">
        <div class="ns-tp__kickrow"><span class="ns-tp__kicker"><x-nx.icon name="zap" /> Airtime and data</span><i class="ns-tp__rule" aria-hidden="true"></i></div>
        @php
            $words = preg_split('/\s+/', trim($hero['title'])) ?: [];
            $last = array_pop($words);
        @endphp
        <h1 class="ns-h1 ns-tp__h1">{{ implode(' ', $words) }} <span class="ns-tp__hl">{{ $last }}</span></h1>
        <p class="ns-sub">{{ $hero['description'] }}</p>
        <ul class="ns-tp__points">
            <li><span class="ns-tp__ico"><x-nx.icon name="shield-check" /></span><span>Finds the network from the number</span></li>
            <li><span class="ns-tp__ico"><x-nx.icon name="signal" /></span><span>Every airtime and data offer for that line</span></li>
            <li><span class="ns-tp__ico"><x-nx.icon name="refresh" /></span><span>Auto-reload daily, weekly or monthly</span></li>
        </ul>
    </div>
    @if (count(\App\Support\Topup\TopupStage::data()['countries']) > 0)
        <div class="ns-tp__art ns-tp__art--stage" wire:ignore><x-topup.logo-stage :compact="true" /></div>
    @else
        <div class="ns-tp__art" aria-hidden="true"><x-topup.hero-art /></div>
    @endif
    <div class="ns-tp__ctas">
    <a href="{{ route('topup.orders') }}" wire:navigate class="ns-tp__cta ns-tp__cta--main"><x-nx.icon name="receipt" /> My top-ups</a>
        @if (\Illuminate\Support\Facades\Route::has('topup.beneficiaries'))
            <a href="{{ route('topup.beneficiaries') }}" wire:navigate class="ns-tp__cta ns-tp__cta--alt"><x-nx.icon name="users" /> Saved numbers</a>
        @endif
    </div>
</section>

@php $slides = array_values(array_filter($hero['slides'], fn ($s) => $s['image'] !== '')); @endphp
@if ($slides)
<section class="ns-tp__car" x-data="{ i: 0, n: {{ count($slides) }}, timer: null,
        go(k) { this.i = (k + this.n) % this.n; const t = this.$refs.track; t.scrollTo({ left: this.i * t.clientWidth, behavior: 'smooth' }); },
        sync() { const t = this.$refs.track; this.i = Math.round(t.scrollLeft / Math.max(1, t.clientWidth)); },
        play() { @if ($hero['autoplay']) this.stop(); this.timer = setInterval(() => this.go(this.i + 1), {{ $hero['interval'] * 1000 }}); @endif },
        stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } } }"
     x-init="play()" @mouseenter="stop()" @mouseleave="play()" @touchstart.passive="stop()" @visibilitychange.window="document.hidden ? stop() : play()"
     role="region" aria-roledescription="carousel" aria-label="Offers" data-tour="topup-banners" wire:ignore>
    <div class="ns-tp__track" x-ref="track" @scroll.passive.debounce.80ms="sync()">
        @foreach ($slides as $k => $s)
            @php $tag = $s['link'] !== '' ? 'a' : 'div'; @endphp
            <{{ $tag }} class="ns-tp__slide" @if ($s['link'] !== '') href="{{ $s['link'] }}" @endif role="group" aria-roledescription="slide" aria-label="Offer {{ $k + 1 }} of {{ count($slides) }}">
                <img src="{{ \Illuminate\Support\Str::startsWith($s['image'], ['http', '/storage', '//']) ? $s['image'] : asset(ltrim($s['image'], '/')) }}" alt="{{ $s['title'] ?: 'Top-up offer' }}" loading="{{ $k === 0 ? 'eager' : 'lazy' }}" decoding="async" draggable="false">
                @if ($s['title'] !== '')<span class="ns-tp__cap">{{ $s['title'] }}</span>@endif
            </{{ $tag }}>
        @endforeach
    </div>
    @if (count($slides) > 1)
        <button type="button" class="ns-tp__nav ns-tp__nav--l" @click="go(i - 1)" aria-label="Previous offer"><x-nx.icon name="chevron-left" /></button>
        <button type="button" class="ns-tp__nav ns-tp__nav--r" @click="go(i + 1)" aria-label="Next offer"><x-nx.icon name="chevron-right" /></button>
        <div class="ns-tp__dots" role="tablist" aria-label="Choose offer">
            @foreach ($slides as $k => $s)<button type="button" role="tab" :aria-selected="i === {{ $k }}" :class="i === {{ $k }} ? 'is-on' : ''" @click="go({{ $k }})" aria-label="Offer {{ $k + 1 }}"></button>@endforeach
        </div>
    @endif
</section>
@endif
@endif
