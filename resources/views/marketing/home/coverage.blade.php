{{-- Coverage (CMS: home.coverage) — the NAVY scene: background shifts dark on
     scroll (data-bg drives the .mkt-bg wrapper). --}}
<section class="bg-navy px-4 py-24 text-slate-100" data-bg="navy">
    <div class="mx-auto max-w-4xl text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $s['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold sm:text-5xl">{{ $s['headline'] }}</h2>
        <p data-reveal class="mx-auto mt-5 max-w-2xl leading-relaxed opacity-90">{{ $s['text'] }}</p>

        <p data-reveal class="mt-8 text-sm font-semibold tracking-wide text-accent">{{ $s['highlights'] }}</p>

        @if (! empty($s['image']))
            <img data-reveal src="{{ $s['image'] }}" alt="Coverage map" class="mx-auto mt-8 w-full max-w-3xl rounded-2xl shadow-2xl">
        @endif

        <div data-reveal class="mx-auto mt-10 max-w-xl rounded-2xl border border-white/15 bg-white/5 p-5 text-sm leading-relaxed backdrop-blur">
            {{ $s['callout'] }}
        </div>

        <a data-reveal href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--gold mt-10 !px-8 !py-3">{{ $s['cta'] }}</a>
    </div>
</section>
