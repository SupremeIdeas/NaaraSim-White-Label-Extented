{{-- Shared error-page shell (Module: error handling). One coherent, brand-styled
     layer for 404/419/500/503 — self-contained (inline CSS, no app/DB/asset
     dependency, so it renders even when the app is broken) and theme-aware.
     NEVER expose a stack trace, file path, package name, or version here.
     Props: $badge, $title, $message, $back (bool, default true). --}}
@php($back = $back ?? true)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name', 'NaaraSim') }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f1f5f9; color: #0D1B2A; padding: 1.5rem; }
        @media (prefers-color-scheme: dark) { body { background: #0D1B2A; color: #e2e8f0; } }
        .card { max-width: 440px; width: 100%; text-align: center; background: #fff; border-radius: 1rem;
            padding: 2.5rem 2rem; box-shadow: 0 10px 40px -12px rgba(10,110,110,.25); }
        @media (prefers-color-scheme: dark) { .card { background: #101d33; box-shadow: none; border: 1px solid #22314e; } }
        .badge { display: inline-flex; align-items: center; gap: .4rem; font-size: .75rem; font-weight: 700;
            color: #0A6E6E; background: rgba(10,110,110,.1); padding: .3rem .7rem; border-radius: 999px; }
        @media (prefers-color-scheme: dark) { .badge { color: #2dd4bf; } }
        h1 { font-size: 1.35rem; margin: 1rem 0 .5rem; }
        p { color: #64748b; line-height: 1.6; margin: 0 0 1.5rem; font-size: .95rem; }
        @media (prefers-color-scheme: dark) { p { color: #94a3b8; } }
        .btns { display: flex; gap: .6rem; justify-content: center; flex-wrap: wrap; }
        a.btn { text-decoration: none; font-weight: 600; font-size: .9rem; padding: .7rem 1.3rem; border-radius: .6rem; }
        .primary { background: #0A6E6E; color: #fff; }
        .ghost { color: #0A6E6E; box-shadow: inset 0 0 0 1.5px rgba(10,110,110,.5); }
        @media (prefers-color-scheme: dark) { .ghost { color: #2dd4bf; box-shadow: inset 0 0 0 1.5px rgba(94,234,212,.5); } }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">{{ $badge }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="btns">
            @if ($back)
                <a href="javascript:history.back()" class="btn primary">Go back</a>
                <a href="{{ url('/') }}" class="btn ghost">Home</a>
            @else
                <a href="{{ url('/') }}" class="btn primary">Home</a>
            @endif
        </div>
    </div>
</body>
</html>
