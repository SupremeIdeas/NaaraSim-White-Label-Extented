{{-- Shared error-page shell. One premium, responsive, self-contained layer for every error page (401/403/404/419/429/500/503): inline CSS and inline SVG only, no app, database
     or asset dependency, so it renders even when the application itself is broken. Light and dark follow the visitor's system setting. SVG icons only, no emoji.
     NEVER expose a stack trace, file path, package name or version here.
     Props: $code, $actionUrl + $actionLabel (optional primary button), $icon (compass|alert|wrench|lock|door|clock|gauge|phone), $badge, $title, $message, $back (bool), $ref (reference id, optional), $retry (seconds, optional), $refresh (auto-reload seconds, optional). --}}
@php
    $back = $back ?? true;
    $ref = $ref ?? null;
    $retry = isset($retry) ? max(0, (int) $retry) : null;
    $refresh = isset($refresh) ? max(10, (int) $refresh) : null;
    $icon = $icon ?? 'alert';
    $actionUrl = $actionUrl ?? null;
    $actionLabel = $actionLabel ?? 'Continue';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="light dark">
    @if ($refresh)<meta http-equiv="refresh" content="{{ $refresh }}">@endif
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <style>
        :root { --c1: #A21CAF; --c2: #7C3AED; --bg: #f4f6fa; --card: rgba(255,255,255,.82); --line: rgba(13,27,42,.09); --ink: #0D1B2A; --muted: #5b6b82; --chip: rgba(13,27,42,.05); --muted2: #8aa0ba; --paper: #ffffff; --paper2: #eef2f8; color-scheme: light dark; }
        @media (prefers-color-scheme: dark) { :root { --bg: #08111f; --card: rgba(16,29,51,.78); --line: rgba(255,255,255,.09); --ink: #eaf0f8; --muted: #9db0c8; --chip: rgba(255,255,255,.06); --paper: #16263f; --paper2: #1d3150; } }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; position: relative; overflow-x: hidden;
            padding: max(1.25rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1.25rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .bg i { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .5; }
        .bg i:nth-child(1) { width: 46vmax; height: 46vmax; left: -14vmax; top: -16vmax; background: var(--c1); opacity: .22; animation: drift 18s ease-in-out infinite alternate; }
        .bg i:nth-child(2) { width: 38vmax; height: 38vmax; right: -12vmax; bottom: -14vmax; background: var(--c2); opacity: .2; animation: drift 22s ease-in-out infinite alternate-reverse; }
        .bg::after { content: ""; position: absolute; inset: 0; background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 44px 44px; -webkit-mask-image: radial-gradient(ellipse at center, #000 20%, transparent 72%); mask-image: radial-gradient(ellipse at center, #000 20%, transparent 72%); opacity: .55; }
        @keyframes drift { to { transform: translate3d(3vmax, 2vmax, 0) scale(1.08); } }
        main { position: relative; z-index: 1; width: min(100%, 31rem); text-align: center; background: var(--card); border: 1px solid var(--line); border-radius: 1.75rem;
            padding: clamp(1.75rem, 6vw, 2.75rem) clamp(1.25rem, 6vw, 2.5rem) clamp(1.5rem, 5vw, 2.25rem);
            -webkit-backdrop-filter: blur(18px) saturate(1.2); backdrop-filter: blur(18px) saturate(1.2); box-shadow: 0 30px 80px -30px rgba(13,27,42,.35), 0 2px 0 rgba(255,255,255,.5) inset; overflow: hidden; }
        .code { position: absolute; left: 50%; top: -.08em; transform: translateX(-50%); font-weight: 900; letter-spacing: -.06em; line-height: 1; font-size: clamp(4.6rem, 20vw, 7.4rem);
            background: linear-gradient(180deg, var(--c1), transparent 82%); -webkit-background-clip: text; background-clip: text; color: transparent; opacity: .22; user-select: none; pointer-events: none; white-space: nowrap; }
        .art { width: min(108%, 22rem); margin: clamp(2.2rem, 10vw, 3rem) -4% .3rem; position: relative; }
        .art svg { display: block; width: 100%; height: auto; overflow: visible; }
        .art .bob, .art .jolt, .art .tw, .art .spin, .art .spin2, .art .needle, .art .bar, .art .blink, .art .sand { transform-box: fill-box; }
        .art .bob { animation: bob 4.2s ease-in-out infinite; }
        .art .jolt { animation: jolt 2.6s ease-in-out infinite; }
        .art .tw { animation: tw 3s ease-in-out infinite; }
        .art .spin { animation: spin 14s linear infinite; transform-box: view-box; }
        .art .spin2 { animation: spin 11s linear infinite reverse; transform-box: view-box; }
        .art .needle { animation: needle 2.4s ease-in-out infinite alternate; transform-box: view-box; }
        .art .bar { animation: bar 3.2s ease-in-out infinite alternate; }
        .art .blink { animation: blink 1.2s steps(2, jump-none) infinite; }
        .art .sand { stroke-dashoffset: 0; animation: sand 1s linear infinite; }
        @keyframes bob { 50% { transform: translateY(-6px); } }
        @keyframes jolt { 0%, 86%, 100% { transform: translateX(0); } 90% { transform: translateX(-3px); } 94% { transform: translateX(3px); } }
        @keyframes tw { 50% { opacity: .25; } }
        @keyframes needle { from { transform: rotate(-12deg); } to { transform: rotate(14deg); } }
        @keyframes bar { to { width: 104px; } }
        @keyframes blink { 50% { opacity: .2; } }
        @keyframes sand { to { stroke-dashoffset: -16; } }
        @keyframes spin { to { transform: rotate(360deg); } }
        .badge { display: inline-flex; align-items: center; gap: .4rem; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--c1); background: color-mix(in srgb, var(--c1) 12%, transparent);
            padding: .35rem .8rem; border-radius: 999px; }
        @media (prefers-color-scheme: dark) { .badge { color: color-mix(in srgb, var(--c1) 55%, #fff); } }
        h1 { margin: 1rem 0 .6rem; font-size: clamp(1.4rem, 5.5vw, 1.85rem); line-height: 1.2; letter-spacing: -.02em; text-wrap: balance; }
        p.msg { margin: 0 auto 1.4rem; max-width: 27rem; color: var(--muted); font-size: clamp(.93rem, 3.6vw, 1rem); line-height: 1.65; text-wrap: pretty; }
        .ref { display: inline-flex; align-items: center; gap: .55rem; margin: 0 auto 1.4rem; padding: .4rem .5rem .4rem .85rem; border-radius: .8rem; background: var(--chip); border: 1px solid var(--line); font-size: .8rem; color: var(--muted); max-width: 100%; flex-wrap: wrap; justify-content: center; }
        .ref code { font: 600 .85rem ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--ink); letter-spacing: 0; white-space: nowrap; }
        .ref button { all: unset; cursor: pointer; font-size: .75rem; font-weight: 700; color: var(--c1); padding: .3rem .6rem; border-radius: .55rem; background: color-mix(in srgb, var(--c1) 12%, transparent); }
        .ref button:hover { background: color-mix(in srgb, var(--c1) 22%, transparent); }
        .count { margin: -.4rem 0 1.2rem; font-size: .85rem; color: var(--muted); }
        .count b { color: var(--ink); font-variant-numeric: tabular-nums; }
        .btns { display: flex; gap: .7rem; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; min-height: 2.9rem; padding: 0 1.5rem; border-radius: .9rem; font: 650 .93rem/1 inherit; text-decoration: none; transition: transform .15s, box-shadow .2s, background .2s; }
        .btn svg { width: 1.05rem; height: 1.05rem; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .primary { color: #fff; background: linear-gradient(135deg, var(--c1), var(--c2)); box-shadow: 0 12px 28px -12px var(--c1); }
        .primary:hover { transform: translateY(-1px); box-shadow: 0 16px 32px -12px var(--c1); }
        .ghost { color: var(--ink); background: var(--chip); border: 1px solid var(--line); }
        .ghost:hover { background: color-mix(in srgb, var(--c1) 10%, var(--chip)); }
        .btn:focus-visible, .ref button:focus-visible, .links a:focus-visible { outline: 3px solid color-mix(in srgb, var(--c1) 70%, #fff); outline-offset: 2px; }
        .links { display: flex; justify-content: center; gap: 1.1rem; flex-wrap: wrap; list-style: none; margin: 1.5rem 0 0; padding: 1.1rem 0 0; border-top: 1px solid var(--line); }
        .links a { color: var(--muted); font-size: .84rem; text-decoration: none; font-weight: 550; }
        .links a:hover { color: var(--c1); }
        footer { position: relative; z-index: 1; margin-top: 1.25rem; font-size: .75rem; color: var(--muted); opacity: .85; text-align: center; }
        @media (max-width: 380px) { main { border-radius: 1.4rem; } .btn { width: 100%; } }
        @media (max-height: 520px) and (orientation: landscape) { body { place-items: start center; } .art { width: 9rem; margin-top: 2rem; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"><i></i><i></i></div>
    <div>
        <main role="main">
            <div class="code" aria-hidden="true">{{ $code }}</div>
            <div class="art" aria-hidden="true">@include('errors.partials.art', ['name' => $icon])</div>
            <span class="badge">{{ $badge }}</span>
            <h1>{{ $title }}</h1>
            <p class="msg">{{ $message }}</p>
            @if ($retry !== null)<p class="count">Try again in <b id="n">{{ $retry }}</b> seconds</p>@endif
            @if ($ref)
                <div class="ref"><span>Reference</span> <code id="ref">{{ $ref }}</code> <button type="button" id="copy" aria-label="Copy reference">Copy</button></div>
            @endif
            <div class="btns">
                @if ($actionUrl)
                    <a href="{{ $actionUrl }}" class="btn primary"><svg viewBox="0 0 24 24"><path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/></svg>{{ $actionLabel }}</a>
                    <a href="{{ url('/') }}" class="btn ghost"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/></svg>Home</a>
                @elseif ($back)
                    <a href="javascript:history.back()" class="btn primary"><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>Go back</a>
                    <a href="{{ url('/') }}" class="btn ghost"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/></svg>Home</a>
                @else
                    <a href="{{ url('/') }}" class="btn primary"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/></svg>Home</a>
                    <a href="javascript:location.reload()" class="btn ghost"><svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.5-5.8"/><path d="M20 4v5h-5"/></svg>Try again</a>
                @endif
            </div>
            <ul class="links">
                <li><a href="{{ url('/support') }}">Help and support</a></li>
                <li><a href="{{ url('/') }}">Start page</a></li>
            </ul>
        </main>
        <footer>{{ config('app.name') }}</footer>
    </div>
    @if ($ref || $retry !== null)
    <script>
        (function () {
            var b = document.getElementById('copy'), r = document.getElementById('ref');
            if (b && r) { b.addEventListener('click', function () { var t = r.textContent; (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { b.textContent = 'Copied'; }, function () { var s = window.getSelection(), g = document.createRange(); g.selectNodeContents(r); s.removeAllRanges(); s.addRange(g); b.textContent = 'Select and copy'; }); }); }
            var n = document.getElementById('n');
            if (n) { var left = parseInt(n.textContent, 10) || 0; var t = setInterval(function () { left--; if (left <= 0) { clearInterval(t); location.reload(); } else { n.textContent = left; } }, 1000); }
        })();
    </script>
    @endif
</body>
</html>
