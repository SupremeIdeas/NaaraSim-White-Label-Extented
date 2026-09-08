@php
    /**
     * Hero section (Section Builder §2) — the flagship type. One partial serves
     * all 5 presets × 3 background modes, driven purely by $config. No preset
     * hard-codes copy; the admin owns every string. Background modes:
     *   animation → CSS brand-gradient / spotlight drift (reduced-motion safe)
     *   image     → a single background image + scrim
     *   images    → auto-advancing image slideshow + scrim
     *   static    → flat scheme colour, no motion
     */
    $c = $config ?? [];
    $preset  = $c['preset']  ?? 'aurora';
    $mode    = $c['mode']    ?? 'animation';
    $scheme  = $c['scheme']  ?? 'brand';
    $align   = ($c['align'] ?? 'center') === 'left' ? 'left' : 'center';
    $images  = array_values(array_filter((array) ($c['images'] ?? [])));
    $hasMedia = in_array($mode, ['image', 'images'], true) && ! empty($images);

    $p1 = \App\Support\PageSections::target($c['cta_primary_target'] ?? '');
    $p2 = \App\Support\PageSections::target($c['cta_secondary_target'] ?? '');
    $p1l = trim((string) ($c['cta_primary_label'] ?? ''));
    $p2l = trim((string) ($c['cta_secondary_label'] ?? ''));

    // Copy sits on a scrim over media; otherwise it follows the scheme.
    $overMedia = $hasMedia;
    $schemeClass = 'is-'.$scheme;
    $modeClass = 'mode-'.$mode;
@endphp

<section class="nx-sechero nx-sechero--{{ $preset }} {{ $schemeClass }} {{ $modeClass }} {{ $align === 'center' ? 'is-center' : 'is-left' }} {{ $overMedia ? 'is-over-media' : '' }}"
    @if ($mode === 'image' && $hasMedia) style="--nx-hero-img: url('{{ $images[0] }}');" @endif
    @if ($mode === 'images' && count($images) > 1) x-data="nxHeroSlides({{ count($images) }})" @endif
    role="region" aria-label="{{ $c['headline'] ?? 'Hero' }}">

    {{-- Image slideshow layer --}}
    @if ($mode === 'images' && $hasMedia)
        <div class="nx-sechero__media" aria-hidden="true">
            @foreach ($images as $i => $src)
                <div class="nx-sechero__slide {{ $i === 0 ? 'is-active' : '' }}"
                     @if (count($images) > 1) :class="active === {{ $i }} && 'is-active'" @endif
                     style="background-image: url('{{ $src }}');"></div>
            @endforeach
        </div>
    @endif

    {{-- Animated / spotlight motion layer (CSS-only, frozen under reduced-motion) --}}
    @if ($mode === 'animation')
        <div class="nx-sechero__aurora" aria-hidden="true"></div>
    @endif

    <div class="nx-sechero__inner">
        @if (! empty($c['eyebrow']))
            <span class="nx-sechero__eyebrow">{{ $c['eyebrow'] }}</span>
        @endif
        <h1 class="nx-sechero__headline">{{ $c['headline'] ?? '' }}</h1>
        @if (! empty($c['subheadline']))
            <p class="nx-sechero__sub">{{ $c['subheadline'] }}</p>
        @endif

        @if ($p1l !== '' || $p2l !== '')
            <div class="nx-sechero__ctas">
                @if ($p1l !== '')
                    <a href="{{ $p1 ?: '#' }}" class="nx-sechero__cta is-primary">{{ $p1l }}</a>
                @endif
                @if ($p2l !== '')
                    <a href="{{ $p2 ?: '#' }}" class="nx-sechero__cta is-ghost">{{ $p2l }}</a>
                @endif
            </div>
        @endif
    </div>

    {{-- Slideshow dots --}}
    @if ($mode === 'images' && count($images) > 1)
        <div class="nx-sechero__dots">
            @foreach ($images as $i => $src)
                <button type="button" @click="go({{ $i }})" aria-label="Slide {{ $i + 1 }}"
                        :class="active === {{ $i }} ? 'is-on' : ''"></button>
            @endforeach
        </div>
    @endif
</section>

@once
    <script>
        // Global Alpine factory (matches the esim-hero pattern already proven in
        // this codebase — Livewire starts Alpine early, so we expose a window fn
        // and reference it from x-data rather than binding an alpine:init hook).
        window.nxHeroSlides = function (count) {
            return {
                active: 0, timer: null,
                init() {
                    if (count > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        this.timer = setInterval(() => { this.active = (this.active + 1) % count; }, 5000);
                    }
                },
                go(i) { this.active = i; clearInterval(this.timer); },
                destroy() { clearInterval(this.timer); },
            };
        };
    </script>
@endonce
