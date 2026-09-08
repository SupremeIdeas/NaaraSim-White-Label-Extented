@php
    $s = $settings;
    $total = max(1200, (int) ($s['animation_total_duration'] ?? 3000));
    $logoSpeed = max(150, (int) ($s['logo_reveal_speed'] ?? 600));
    $taglineDelay = max(0, (int) ($s['tagline_reveal_delay'] ?? 800));
    $aurora = max(3, (int) ($s['aurora_speed'] ?? 8));
    $c1 = $s['brand_color_1'] ?? '#0A6E6E';
    $c2 = $s['brand_color_2'] ?? '#D4A017';
    // Which entrance style: 'aurora' (drifting blobs) or 'spotlight' (radial beam).
    $style = in_array(($s['style'] ?? 'aurora'), ['aurora', 'spotlight'], true) ? $s['style'] : 'aurora';
    // Start the blur-out a beat before we redirect (spec: ~200ms before the end).
    $leaveAt = max(600, $total - 300);
@endphp

{{-- Aurora Welcome entrance. CSS drives the visuals (blobs drift, logo zooms in
     from distance, tagline fades up); Alpine only handles timing → redirect.
     100% CSS + Alpine, no external JS. --}}
<div
    x-data
    x-init="
        setTimeout(() => $el.classList.add('is-leaving'), {{ $leaveAt }});
        setTimeout(() => $wire.redirectToDashboard(), {{ $total }});
    "
    {{-- Always-dark overlay by design (an aurora only reads on black); the
         explicit dark:bg-black keeps it identical in either theme. --}}
    class="nx-welcome nx-welcome--{{ $style }} fixed inset-0 z-[9999] flex items-center justify-center overflow-hidden bg-black dark:bg-black"
    style="--c1: {{ $c1 }}; --c2: {{ $c2 }}; --aurora-speed: {{ $aurora }}s; --logo-speed: {{ $logoSpeed }}ms; --tagline-delay: {{ $taglineDelay }}ms;"
    role="dialog" aria-label="Welcome"
>
    @if ($style === 'spotlight')
        {{-- Spotlight entrance: a single brand-tinted radial beam sweeps in behind
             the mark, with a slow conic shimmer — cleaner and more "premium hero"
             than the drifting aurora, for brands that want a calmer first login. --}}
        <div class="nx-spot__beam" aria-hidden="true"></div>
        <div class="nx-spot__ring" aria-hidden="true"></div>
    @else
        {{-- Aurora blobs — white core blending to brand colours, screen-blended. --}}
        <div class="nx-welcome__blob nx-welcome__blob--1 bg-gradient-to-r from-white via-[var(--c1)] to-[var(--c2)]" aria-hidden="true"></div>
        <div class="nx-welcome__blob nx-welcome__blob--2 bg-gradient-to-l from-white via-[var(--c2)] to-[var(--c1)]" aria-hidden="true"></div>
        <div class="nx-welcome__blob nx-welcome__blob--3 bg-gradient-to-b from-white/30 to-transparent" aria-hidden="true"></div>
    @endif

    {{-- Content --}}
    <div class="nx-welcome__content relative z-10 px-6 text-center">
        <div class="nx-welcome__logo mb-6 flex justify-center">
            <x-brand-logo variant="family" theme="dark" size="xl" fallback-icon="signal" />
        </div>
        <h1 class="nx-welcome__title text-3xl font-bold text-white md:text-5xl">
            {{ $s['welcome_text'] ?? 'Welcome to' }}
        </h1>
        <p class="nx-welcome__tagline mt-4 text-lg text-white/70 md:text-xl">
            {{ $s['tagline_text'] ?? "Let's get you started" }}
        </p>
    </div>

    @once
        <style>
            .nx-welcome { animation: nxWelcomeIn 300ms ease forwards; }
            .nx-welcome.is-leaving { animation: nxWelcomeOut 300ms ease-in forwards; }

            .nx-welcome__blob {
                position: absolute;
                border-radius: 9999px;
                filter: blur(80px);
                mix-blend-mode: screen;
                pointer-events: none;
            }
            .nx-welcome__blob--1 { width: 600px; height: 600px; opacity: 0.40; animation: nxAur1 var(--aurora-speed) ease-in-out infinite; }
            .nx-welcome__blob--2 { width: 500px; height: 500px; opacity: 0.30; animation: nxAur2 calc(var(--aurora-speed) + 2s) ease-in-out infinite; }
            .nx-welcome__blob--3 { width: 700px; height: 700px; opacity: 0.20; filter: blur(120px); animation: nxAur3 calc(var(--aurora-speed) + 4s) ease-in-out infinite; }

            /* Logo zooms in fast from deep distance: scale 3 → 1, opacity 0 → 1. */
            .nx-welcome__logo {
                opacity: 0;
                transform: scale(3);
                filter: drop-shadow(0 0 40px rgba(255,255,255,0.55));
                animation: nxLogoZoom var(--logo-speed) cubic-bezier(0.2, 0.8, 0.2, 1) 150ms forwards;
            }
            .nx-welcome__title {
                opacity: 0;
                animation: nxFadeUp 500ms ease-out calc(150ms + var(--logo-speed)) forwards;
            }
            .nx-welcome__tagline {
                opacity: 0;
                animation: nxFadeUp 500ms ease-out var(--tagline-delay) forwards;
            }

            @keyframes nxWelcomeIn  { from { opacity: 0; } to { opacity: 1; } }
            @keyframes nxWelcomeOut { from { opacity: 1; filter: blur(0); } to { opacity: 0; filter: blur(12px); } }
            @keyframes nxLogoZoom   { to { opacity: 1; transform: scale(1); } }
            @keyframes nxFadeUp     { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes nxAur1 { 0%,100% { transform: translate(-20%,-10%) scale(1) rotate(0); } 50% { transform: translate(20%,10%) scale(1.2) rotate(180deg); } }
            @keyframes nxAur2 { 0%,100% { transform: translate(10%,20%) scale(1) rotate(0); } 50% { transform: translate(-10%,-20%) scale(1.1) rotate(-180deg); } }
            @keyframes nxAur3 { 0%,100% { transform: scale(1); } 50% { transform: scale(1.3); } }

            /* ---- Spotlight style (second entrance) ---- */
            /* A brand-tinted radial beam grows from the centre, with a slow
               conic-gradient ring shimmer behind the mark. */
            .nx-spot__beam {
                position: absolute; inset: 0; pointer-events: none;
                background: radial-gradient(circle at 50% 46%,
                    rgba(255,255,255,0.28) 0%,
                    color-mix(in srgb, var(--c1) 55%, transparent) 22%,
                    color-mix(in srgb, var(--c2) 30%, transparent) 42%,
                    transparent 66%);
                opacity: 0; transform: scale(0.6);
                animation: nxSpotIn 900ms cubic-bezier(0.2,0.8,0.2,1) 100ms forwards;
            }
            .nx-spot__ring {
                position: absolute; width: 130vmin; height: 130vmin; border-radius: 9999px;
                pointer-events: none; opacity: 0.22; filter: blur(2px);
                background: conic-gradient(from 0deg,
                    transparent 0deg, var(--c1) 90deg, transparent 180deg, var(--c2) 270deg, transparent 360deg);
                mask: radial-gradient(circle, transparent 54%, #000 55%, #000 60%, transparent 61%);
                -webkit-mask: radial-gradient(circle, transparent 54%, #000 55%, #000 60%, transparent 61%);
                animation: nxSpotSpin calc(var(--aurora-speed) * 2) linear infinite;
            }
            /* On spotlight the mark glows in brand teal rather than pure white. */
            .nx-welcome--spotlight .nx-welcome__logo {
                filter: drop-shadow(0 0 34px color-mix(in srgb, var(--c1) 70%, white));
            }
            @keyframes nxSpotIn  { to { opacity: 1; transform: scale(1); } }
            @keyframes nxSpotSpin { to { transform: rotate(360deg); } }

            @media (prefers-reduced-motion: reduce) {
                .nx-welcome__blob { animation: none !important; }
                .nx-spot__ring { animation: none !important; }
                .nx-spot__beam { animation: none !important; opacity: 1; transform: none; }
                .nx-welcome__logo { animation: none !important; opacity: 1; transform: none; }
                .nx-welcome__title, .nx-welcome__tagline { animation: none !important; opacity: 1; }
            }
        </style>
    @endonce
</div>
