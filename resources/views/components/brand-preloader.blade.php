{{-- Brand preloader (Module 26 + Preloader Studio). A full-screen overlay shown
     until the page finishes loading, then faded out. The visual is resolved
     per-PAGE-TYPE via PreloaderSettings::forPageType(): until an admin saves an
     assignment in Preloader Studio, forPageType() returns the exact legacy
     behaviour (BrandSettings enabled + style), so this is unchanged on every
     existing install. Configured page types can pick any Studio preset.

     Self-removing on `load` with a hard timeout fallback so it can never trap the
     page, and reduced-motion-aware. The background is painted inline before
     Alpine boots, so there is no theme flash. --}}
@props(['pageType' => 'default'])
@php
    $cfg = \App\Support\PreloaderSettings::forPageType($pageType);
    // Admin-only debug preview (BLUEPRINT §7.2): ?preloader_preview=slug forces a
    // preset on any page so the port can be verified preset-by-preset. Gated to
    // privileged users, so it can never be triggered by a public visitor.
    $nxPreview = request()->query('preloader_preview');
    if ($nxPreview && ($u = auth()->user()) && $u->hasAnyRole(['super_admin', 'admin'])) {
        if (\App\Support\PreloaderSettings::isLegacy($nxPreview) || isset(\App\Support\PreloaderSettings::PRESETS[$nxPreview])) {
            $cfg = array_merge(\App\Support\PreloaderSettings::safeDefault(), ['preset' => $nxPreview, 'enabled' => true]);
        }
    }
@endphp
@if (! empty($cfg['enabled']))
    @php($isLegacy = \App\Support\PreloaderSettings::isLegacy($cfg['preset']))
    @php($nxPreFavicon = \App\Support\BrandSettings::favicon())
    @php($vars = \App\Support\PreloaderSettings::resolveCssVars($cfg))
    @php($bgRgb = ! empty($cfg['bg_color']) ? \App\Support\BrandSettings::hexToChannels($cfg['bg_color']) : null)
    @php($bg = $bgRgb ? 'rgb('.$bgRgb.' / '.($cfg['bg_opacity'] ?? 1).')' : 'rgb(var(--brand-navy))')
    <div id="nx-preloader" role="status" aria-label="Loading"
         style="position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;background:{{ $bg }};transition:opacity .4s ease;{{ $isLegacy ? '' : $vars.'backdrop-filter:blur(var(--nx-pl-blur,0));-webkit-backdrop-filter:blur(var(--nx-pl-blur,0));' }}">

        @if ($isLegacy)
            @php($nxPreStyle = $cfg['preset'])
            @if ($nxPreStyle === 'pulse-logo' && $nxPreFavicon)
                {{-- Impulse logo mark (shared .nx-pulse motif, compiled in app.css). --}}
                <span class="nx-pulse" style="width:72px;height:72px;">
                    <img src="{{ $nxPreFavicon }}" alt="" width="72" height="72" draggable="false">
                </span>
            @elseif ($nxPreStyle === 'bars')
                <span class="nx-pre-bars"><i></i><i></i><i></i><i></i></span>
            @elseif ($nxPreStyle === 'progress')
                {{-- Determinate-feel progress bar: eases toward 100% while the page
                     loads, then snaps full on `load` (JS below). Logo mark sits above
                     it when a favicon exists, so the brand still reads. --}}
                <span class="nx-pre-progress-wrap">
                    @if ($nxPreFavicon)
                        <img class="nx-pre-progress-logo" src="{{ $nxPreFavicon }}" alt="" width="56" height="56" draggable="false">
                    @endif
                    <span class="nx-pre-progress"><i></i></span>
                </span>
            @else
                <span class="nx-pre-spin"></span>
            @endif
        @elseif (view()->exists('components.preloaders.'.$cfg['preset']))
            {{-- Studio preset: markup-only partial, all CSS compiled in preloaders.css. --}}
            <div class="nx-pl nx-pl--{{ $cfg['preset'] }}" style="opacity:var(--nx-pl-opacity,1);transform:scale(var(--nx-pl-scale,1));">
                @include('components.preloaders.'.$cfg['preset'], ['cfg' => $cfg])
            </div>
        @else
            {{-- Assigned preset partial not present (defensive) — safe spinner. --}}
            <span class="nx-pre-spin"></span>
        @endif

        <style>
            /* Spinner ring. */
            .nx-pre-spin { display:block; width:3rem; height:3rem; border-radius:9999px;
                border:3px solid rgba(255,255,255,.18); border-top-color:rgb(var(--brand-accent));
                animation:nx-preload-spin .8s linear infinite; }
            @keyframes nx-preload-spin { to { transform:rotate(360deg); } }

            /* Bars. */
            .nx-pre-bars { display:flex; gap:.4rem; align-items:flex-end; height:2.75rem; }
            .nx-pre-bars i { width:.4rem; height:100%; border-radius:.25rem; background:rgb(var(--brand-accent));
                animation:nx-pre-bar 1s ease-in-out infinite; }
            .nx-pre-bars i:nth-child(2){ animation-delay:.15s } .nx-pre-bars i:nth-child(3){ animation-delay:.3s } .nx-pre-bars i:nth-child(4){ animation-delay:.45s }
            @keyframes nx-pre-bar { 0%,100%{ transform:scaleY(.4); opacity:.6 } 50%{ transform:scaleY(1); opacity:1 } }

            /* Progress bar. */
            .nx-pre-progress-wrap { display:flex; flex-direction:column; align-items:center; gap:1.25rem; }
            .nx-pre-progress-logo { border-radius:14px; animation:nx-pre-breathe 1.6s ease-in-out infinite; }
            .nx-pre-progress { display:block; width:min(60vw,200px); height:4px; border-radius:9999px;
                background:rgba(255,255,255,.14); overflow:hidden; }
            .nx-pre-progress i { display:block; height:100%; width:0; border-radius:9999px;
                background:linear-gradient(90deg, rgb(var(--brand-primary)), rgb(var(--brand-accent)));
                transition:width .35s ease-out; }
            @keyframes nx-pre-breathe { 0%,100%{ transform:scale(1); opacity:.9 } 50%{ transform:scale(1.06); opacity:1 } }

            #nx-preloader.is-done { opacity:0; pointer-events:none; }
            @media (prefers-reduced-motion: reduce) {
                #nx-preloader .nx-pre-spin, #nx-preloader .nx-pre-bars i,
                #nx-preloader .nx-pre-progress-logo { animation:none; }
            }
        </style>
    </div>
    <script>
        (function () {
            var el = document.getElementById('nx-preloader');
            if (!el) return;
            var hide = function () {
                el.classList.add('is-done');
                setTimeout(function () { el.remove(); }, 500);
            };

            // Progress style: ease the bar toward ~90% while loading, then snap to
            // 100% on `load` just before fading out (honest, never stuck at 100).
            var bar = el.querySelector('.nx-pre-progress i');
            var timer = null;
            if (bar) {
                var pct = 0;
                timer = setInterval(function () {
                    pct += Math.max(1, (90 - pct) * 0.12); // decelerating approach
                    bar.style.width = Math.min(pct, 90) + '%';
                }, 200);
            }
            var finish = function () {
                if (timer) { clearInterval(timer); timer = null; }
                if (bar) { bar.style.width = '100%'; }
                setTimeout(hide, bar ? 250 : 150);
            };

            window.addEventListener('load', finish);
            setTimeout(finish, 4000); // hard fallback — never trap the page
        })();
    </script>
@endif
