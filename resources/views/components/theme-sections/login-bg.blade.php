@props(['effect' => 'none'])

{{-- Swappable LOGIN BACKGROUND layer (owner request, 2026-09-07): "some
     login bg will have custom unique dot grid material effects and mesh
     grain on some, Aurora bg." A decorative, pointer-events-none,
     absolutely-positioned layer any login style family can drop behind its
     content — `absolute inset-0 z-0` here, paired with `relative z-10` on
     the content wrapping it. Resolved via
     ThemePreset::sectionStyle('login_bg'); 'none' renders nothing (today's
     exact look, zero regression). Pure CSS/SVG — no images, no JS, so it
     costs nothing extra to ship and works identically on mobile. --}}
@if ($effect === 'dot-grid')
    <div class="pointer-events-none absolute inset-0 z-0 opacity-[0.35] dark:opacity-[0.25]" aria-hidden="true"
         style="background-image: radial-gradient(rgb(var(--brand-primary)) 1px, transparent 1px); background-size: 22px 22px;">
    </div>
@elseif ($effect === 'mesh-grain')
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
        {{-- Soft colour-mesh blobs (brand primary/accent), independent of
             any one theme's structural layout. --}}
        <div class="absolute inset-0"
             style="background:
                radial-gradient(38% 45% at 12% 15%, rgb(var(--brand-primary) / 0.30), transparent 60%),
                radial-gradient(42% 50% at 85% 20%, rgb(var(--brand-accent) / 0.28), transparent 60%),
                radial-gradient(50% 55% at 50% 90%, rgb(var(--brand-navy) / 0.22), transparent 60%);
                filter: blur(40px);"></div>
        {{-- Film-grain texture via an inline SVG turbulence filter — the
             standard CSS-only "grain" technique, no image asset needed. --}}
        <div class="absolute inset-0 opacity-[0.12] mix-blend-overlay dark:opacity-[0.18]"
             style="background-image: url('data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cfilter%20id%3D%22n%22%3E%3CfeTurbulence%20type%3D%22fractalNoise%22%20baseFrequency%3D%220.85%22%20numOctaves%3D%222%22%20stitchTiles%3D%22stitch%22%2F%3E%3C%2Ffilter%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20filter%3D%22url(%23n)%22%2F%3E%3C%2Fsvg%3E');"></div>
    </div>
@elseif ($effect === 'aurora')
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
        <div class="nx-aurora-band absolute -inset-x-1/4 -top-1/3 h-[70%] rounded-[100%] bg-gradient-to-r from-primary/40 via-accent/30 to-transparent blur-3xl"></div>
        <div class="nx-aurora-band nx-aurora-band--reverse absolute -inset-x-1/4 top-1/4 h-[60%] rounded-[100%] bg-gradient-to-l from-accent/30 via-primary/25 to-transparent blur-3xl"></div>
    </div>
    @once
        <style>
            @keyframes nx-aurora-drift { 0%, 100% { transform: translateX(-4%) translateY(0); } 50% { transform: translateX(4%) translateY(2%); } }
            .nx-aurora-band { animation: nx-aurora-drift 14s ease-in-out infinite; }
            .nx-aurora-band--reverse { animation-direction: alternate-reverse; animation-duration: 18s; }
            @media (prefers-reduced-motion: reduce) { .nx-aurora-band { animation: none; } }
        </style>
    @endonce
@endif
