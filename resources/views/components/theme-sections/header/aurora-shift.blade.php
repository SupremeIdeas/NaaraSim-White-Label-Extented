{{-- Swappable HEADER — "aurora-shift" style family (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" — deep indigo-violet fintech
     energy with electric-blue highlights on a near-black gradient, a
     trading-terminal read on a travel-money app. Structurally distinct from
     every sibling header: a near-black bar carrying a small live "current"
     equaliser (three animated bars, not a single pulsing dot like
     aries-contrast/solar-flare) beside the wordmark, and — this persona's
     recurring signature, echoed on the bottom nav and footer — a thin
     ANIMATED FLOWING GRADIENT LINE under the whole bar in place of a static
     border or dashed rule, reading as a live current rather than a fixed
     divider. Same inherited variables as header/default.blade.php:
     $brandRoute, $headerBrand, $brandIcon, $headerActions (optional slot). --}}
@once
    <style>
        @keyframes nx-aurora-current { 0% { background-position: 0 0; } 100% { background-position: 200% 0; } }
        .nx-aurora-current-line {
            background-image: linear-gradient(90deg,
                transparent 0%, rgb(var(--brand-accent)) 20%, rgb(var(--brand-primary)) 45%,
                transparent 55%, rgb(var(--brand-accent)) 80%, transparent 100%);
            background-size: 200% 100%;
            animation: nx-aurora-current 3.5s linear infinite;
        }
        @media (prefers-reduced-motion: reduce) { .nx-aurora-current-line { animation: none; background-position: 0 0; } }
        @keyframes nx-aurora-eq { 0%, 100% { transform: scaleY(0.35); } 50% { transform: scaleY(1); } }
        .nx-aurora-eq-bar { animation: nx-aurora-eq 1.1s ease-in-out infinite; transform-origin: bottom; }
        @media (prefers-reduced-motion: reduce) { .nx-aurora-eq-bar { animation: none; transform: scaleY(0.7); } }
    </style>
@endonce
<header class="sticky top-0 z-30 lg:hidden">
    <div data-header-root class="flex items-center justify-between gap-2 bg-navy px-4 py-3">
        <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
            {{-- Live "current" equaliser — three bars ticking at different
                 phases, the header's own signature in place of a plain dot. --}}
            <span class="flex h-4 shrink-0 items-end gap-[3px]" aria-hidden="true">
                <span class="nx-aurora-eq-bar h-full w-[3px] rounded-full bg-accent" style="animation-delay:-0.9s"></span>
                <span class="nx-aurora-eq-bar h-full w-[3px] rounded-full bg-primary" style="animation-delay:-0.4s"></span>
                <span class="nx-aurora-eq-bar h-full w-[3px] rounded-full bg-accent" style="animation-delay:-0.1s"></span>
            </span>
            <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" theme="dark" size="md" :fallback-icon="$brandIcon" />
        </a>
        <div class="flex shrink-0 items-center gap-1 rounded-[0.625rem] border border-white/10 bg-white/5 px-1 py-1">
            {{ $headerActions ?? '' }}
            @unless (isset($headerActions))<x-theme-toggle />@endunless
        </div>
    </div>
    {{-- The flowing current line — this persona's recurring boundary motif. --}}
    <div class="nx-aurora-current-line h-[2px] w-full" aria-hidden="true"></div>
</header>
