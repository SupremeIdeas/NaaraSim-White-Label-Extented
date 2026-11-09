@php $hero = \App\Support\TopupHeroContent::current(); @endphp
@if ($hero['enabled'])
<section class="ns-tp__heroCard" data-tour="topup-hero" aria-label="Top-up">
    <div class="ns-tp__heroTxt">
        <span class="ns-tp__kicker"><x-nx.icon name="zap" /> Airtime and data</span>
        <h1 class="ns-h1 ns-tp__h1">{{ $hero['title'] }}</h1>
        <p class="ns-sub">{{ $hero['description'] }}</p>
        <ul class="ns-tp__points">
            <li><x-nx.icon name="shield-check" /> Finds the network from the number</li>
            <li><x-nx.icon name="signal" /> Every airtime and data offer for that line</li>
            <li><x-nx.icon name="refresh" /> Auto-reload daily, weekly or monthly</li>
        </ul>
        <a href="{{ route('topup.orders') }}" wire:navigate class="ns-tp__history"><x-nx.icon name="receipt" /> My top-ups</a>
    </div>
    <div class="ns-tp__art" aria-hidden="true"><x-topup.hero-art /></div>
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
