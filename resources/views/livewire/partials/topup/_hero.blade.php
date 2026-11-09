@php $hero = \App\Support\TopupHeroContent::current(); @endphp
@if ($hero['enabled'])
@php
    $brand = (string) \App\Models\Setting::getValue('brand.color_primary', '');
    $brand = preg_match('/^#[0-9A-Fa-f]{6}$/', $brand) ? strtoupper($brand) : '#475569';   // the install's own brand colour, a neutral slate until one is set
    $pal = \App\Support\Topup\TopupBrandTint::palette($brand);
    $hv = collect(['l' => $pal['light'], 'd' => $pal['dark']])->flatMap(fn ($q, $m) => ["--h{$m}-base" => $q['base'], "--h{$m}-a1" => $q['a1'], "--h{$m}-a2" => $q['a2'], "--h{$m}-ink" => $q['ink']])->map(fn ($v, $k) => "$k:$v")->implode(';');
    $words = preg_split('/\s+/', trim($hero['title'])) ?: [];
    $last = array_pop($words);
@endphp
<section class="ns-tp__heroCard ns-tp__heroCard--f" data-tour="topup-hero" aria-label="Top-up" style="{{ $hv }}">
    <span class="ns-tp__aurora" aria-hidden="true"></span>
    <div class="ns-tp__heroTxt">
        <div class="ns-tp__kickrow"><span class="ns-tp__kicker"><x-nx.icon name="zap" /> Airtime and data</span><i class="ns-tp__rule" aria-hidden="true"></i></div>
        <h1 class="ns-h1 ns-tp__h1">{{ implode(' ', $words) }} <span class="ns-tp__hl">{{ $last }}</span></h1>
        <p class="ns-sub">{{ $hero['description'] }}</p>
        <ul class="ns-tp__points">
            <li><span class="ns-tp__ico"><x-nx.icon name="shield-check" /></span><span>Finds the network from the number</span></li>
            <li><span class="ns-tp__ico"><x-nx.icon name="signal" /></span><span>Every airtime and data offer for that line</span></li>
            <li><span class="ns-tp__ico"><x-nx.icon name="refresh" /></span><span>Auto-reload daily, weekly or monthly</span></li>
        </ul>
    </div>
    <div class="ns-tp__art" aria-hidden="true"><x-topup.hero-art /></div>
    <div class="ns-tp__ctas">
        <a href="{{ route('topup.orders') }}" wire:navigate class="ns-tp__cta ns-tp__cta--main"><x-nx.icon name="receipt" /> My top-ups</a>
        <a href="{{ route('topup') }}#step-number" class="ns-tp__cta ns-tp__cta--alt"><x-nx.icon name="signal" /> Start a top-up</a>
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
