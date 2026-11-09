{{-- The Airtime and Data hero stage, built to sit BESIDE the hero text: a drawn iPhone whose screen zooms in one real network logo at a time (large, clean, centred),
     walking through every network of a country and then on to the next country, forever. A slim rail on the side of the device lists that country's networks and lights the one on screen,
     so the eye always knows where it is. Data comes from the live catalogue (TopupStage), so it can never show a network the product does not sell. One small Alpine timer drives it;
     reduced-motion shows the first network still. Logos are root-relative committed assets, loaded eagerly so nothing ever pops in blank. --}}
@props(['compact' => false])
@php
    $data = \App\Support\Topup\TopupStage::data();
    $stage = collect($data['countries'] ?? [])->filter(fn ($c) => count($c['networks'] ?? []) >= 2)->take(14)->map(fn ($c) => [
        'iso' => $c['iso'], 'name' => $c['name'], 'total' => $c['networks_total'] ?? count($c['networks']),
        'nets' => collect($c['networks'])->take(5)->map(fn ($n) => ['name' => preg_replace('/\s*\(.*?\)\s*/', '', $n['name']), 'logo' => $n['logo'], 'bg' => $n['bg'] ?? null, 'ini' => $n['initials'], 'tint' => $tn = \App\Support\Topup\TopupBrandTint::for($n['name'], $c['iso']), 'ink' => \App\Support\Topup\TopupBrandTint::ink($tn), 'pal' => \App\Support\Topup\TopupBrandTint::palette($tn)])->values()->all(),
    ])->values()->all();
    // The showcase walks the WORLD: one network from every country, then the next one from every country, so the hero keeps changing country (and colour).
    $seq = [];
    foreach (range(0, 2) as $pass) {
        foreach ($stage as $k => $c) {
            if (isset($c['nets'][$pass])) {
                $seq[] = [$k, $pass];
            }
        }
    }
@endphp
@if (count($stage) > 0)
<div class="tlsw"
     x-data="{ ci: 0, ni: 0, i: 0, seq: @js($seq), tints: @js(array_map(fn ($c) => array_map(fn ($n) => [$n['tint'], $n['ink'], $n['pal']], $c['nets']), $stage)), data: @js(array_map(fn ($c) => count($c['nets']), $stage)), timer: null,
        still: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        step() { this.i = (this.i + 1) % this.seq.length; this.ci = this.seq[this.i][0]; this.ni = this.seq[this.i][1] },
        run() { if (! this.still && ! this.timer) this.timer = setInterval(() => this.step(), 2100) },
        halt() { clearInterval(this.timer); this.timer = null } }"
     x-init="run()" x-effect="window.dispatchEvent(new CustomEvent('tp-tint', { detail: { c: tints[ci] ? tints[ci][ni][0] : '', k: tints[ci] ? tints[ci][ni][1] : '', p: tints[ci] ? tints[ci][ni][2] : null } }))" @visibilitychange.window="document.hidden ? halt() : run()" x-on:beforeunload.window="halt()">
<div class="tls {{ $compact ? 'tls--compact' : '' }}" data-topup-stage role="img" aria-label="Airtime and data for {{ count($stage) }} countries, with every network's logo">
    <span class="tls__halo" aria-hidden="true"></span>

    {{-- the side rail: this country's networks, the one on the phone lit --}}
    <div class="tls__rail" aria-hidden="true">
        @foreach ($stage as $k => $c)
            <ul x-show="ci === {{ $k }}" x-cloak>
                @foreach ($c['nets'] as $j => $n)
                    <li :class="ni === {{ $j }} ? 'is-on' : ''">
                        <span class="tls__chip {{ $n['bg'] ? 'has-bg' : '' }}" @if ($n['bg']) style="--lb:{{ $n['bg'] }}" @endif>
                            @if ($n['logo'])<img src="{{ $n['logo'] }}" alt="" width="48" height="48" loading="eager" decoding="async">@else<b style="background:hsl({{ crc32(strtolower($n['name'])) % 360 }} 62% 38%">{{ $n['ini'] }}</b>@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>

    {{-- the phone: a drawn iPhone with a live clock; the zoomed logo is the screen --}}
    <div class="tls__phone" aria-hidden="true" x-data="{ now: '', tick() { const d = new Date(); this.now = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: false }) } }" x-init="tick(); setInterval(() => tick(), 15000)">
        <svg class="tls__body" viewBox="0 0 300 620" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <defs>
                <linearGradient id="ip-ti" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d9dde3"/><stop offset=".18" stop-color="#6b7078"/><stop offset=".5" stop-color="#2a2d33"/><stop offset=".82" stop-color="#7d838c"/><stop offset="1" stop-color="#c9cdd4"/></linearGradient>
                <linearGradient id="ip-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color: var(--tp-screen, #0b3b43)"/><stop offset=".6" style="stop-color: color-mix(in srgb, var(--tp-screen, #0b3b43) 72%, #000)"/><stop offset="1" style="stop-color: color-mix(in srgb, var(--tp-screen, #0b3b43) 48%, #000)"/></linearGradient>
                <radialGradient id="ip-glow" cx=".5" cy=".18" r=".7"><stop offset="0" style="stop-color: color-mix(in srgb, var(--tp-screen, #19c7bb) 55%, #fff)" stop-opacity=".45"/><stop offset="1" style="stop-color: color-mix(in srgb, var(--tp-screen, #19c7bb) 55%, #fff)" stop-opacity="0"/></radialGradient>
                <linearGradient id="ip-glare" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".16"/><stop offset=".42" stop-color="#fff" stop-opacity=".03"/><stop offset=".43" stop-color="#fff" stop-opacity="0"/></linearGradient>
                <clipPath id="ip-clip"><rect x="11" y="11" width="278" height="598" rx="44"/></clipPath>
                <filter id="ip-sh" x="-20%" y="-10%" width="140%" height="125%"><feDropShadow dx="0" dy="18" stdDeviation="14" flood-color="#000" flood-opacity=".5"/></filter>
            </defs>
            {{-- side buttons --}}
            <g fill="url(#ip-ti)"><rect x="-1.5" y="118" width="4" height="26" rx="2"/><rect x="-1.5" y="168" width="4" height="52" rx="2"/><rect x="-1.5" y="232" width="4" height="52" rx="2"/><rect x="297.5" y="190" width="4" height="82" rx="2"/></g>
            {{-- titanium body, a hairline bezel and the glass --}}
            <g filter="url(#ip-sh)"><rect x="1" y="1" width="298" height="618" rx="54" fill="url(#ip-ti)"/></g>
            <rect x="3.2" y="3.2" width="293.6" height="613.6" rx="52" fill="none" stroke="#fff" stroke-opacity=".28" stroke-width=".8"/>
            <rect x="6" y="6" width="288" height="608" rx="49" fill="#05070a"/>
            <g clip-path="url(#ip-clip)">
                <rect x="11" y="11" width="278" height="598" fill="url(#ip-wall)"/>
                <rect x="11" y="11" width="278" height="598" fill="url(#ip-glow)"/>
                <path d="M11 11 H289 V230 C220 190 110 205 11 290 Z" fill="url(#ip-glare)"/>
            </g>
            {{-- Dynamic Island + camera --}}
            <rect x="106" y="24" width="88" height="26" rx="13" fill="#000"/>
            <circle cx="176" cy="37" r="4.2" fill="#0b1220"/><circle cx="176" cy="37" r="2" fill="#16314f"/><circle cx="175.2" cy="36.2" r=".7" fill="#7fb4ff" opacity=".8"/>
            {{-- home indicator --}}
            <rect x="106" y="594" width="88" height="4.6" rx="2.3" fill="#fff" fill-opacity=".72"/>
        </svg>
        <div class="tls__status"><b x-text="now"></b><span class="tls__sig"><i></i><i></i><i></i><i></i></span><span class="tls__bat"><i></i></span></div>
        <div class="tls__screen">
            @foreach ($stage as $k => $c)
                <div class="tls__country" x-show="ci === {{ $k }}" x-cloak>
                    <x-country-flag :country="$c['iso']" class="h-4 w-6" /><b>{{ $c['name'] }}</b>
                </div>
            @endforeach
            <div class="tls__stage">
                @foreach ($stage as $k => $c)
                    @foreach ($c['nets'] as $j => $n)
                        <div class="tls__slide" x-show="ci === {{ $k }} && ni === {{ $j }}" x-cloak x-transition:leave="tls-out" x-transition:leave-start="tls-out-a" x-transition:leave-end="tls-out-b">
                            <span class="tls__big {{ $n['bg'] ? 'has-bg' : '' }}" @if ($n['bg']) style="--lb:{{ $n['bg'] }}" @endif>
                                @if ($n['logo'])<img src="{{ $n['logo'] }}" alt="" width="160" height="160" loading="eager" decoding="async">@else<b style="background:hsl({{ crc32(strtolower($n['name'])) % 360 }} 62% 38%">{{ $n['ini'] }}</b>@endif
                            </span>
                            <strong>{{ $n['name'] }}</strong>
                            <small>Airtime and data</small>
                        </div>
                    @endforeach
                @endforeach
            </div>
            <span class="tls__dots">@foreach ($stage as $k => $c)<span x-show="ci === {{ $k }}" x-cloak>@foreach ($c['nets'] as $j => $n)<i :class="ni === {{ $j }} ? 'is-on' : ''"></i>@endforeach</span>@endforeach</span>
            <span class="tls__go">Top up</span>
        </div>
    </div>
</div>
    {{-- country ticker: every country, the active one lit --}}
    <ol class="tls__ticker" aria-hidden="true">
        @foreach ($stage as $k => $c)<li :class="ci === {{ $k }} ? 'is-on' : ''"><x-country-flag :country="$c['iso']" class="h-3.5 w-5" /></li>@endforeach
    </ol>
</div>
@endif
