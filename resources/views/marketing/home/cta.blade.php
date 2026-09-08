{{-- Final CTA (CMS: home.cta). --}}
<section class="px-4 pb-24 pt-8" data-bg="light">
    <div data-reveal class="relative mx-auto max-w-4xl overflow-hidden rounded-3xl bg-primary px-6 py-16 text-center text-white shadow-2xl">
        <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-accent/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-16 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
        <h2 class="relative text-3xl font-bold sm:text-4xl">{{ $s['headline'] }}</h2>
        <p class="relative mx-auto mt-4 max-w-xl leading-relaxed text-teal-50">{{ $s['subtext'] }}</p>
        <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--gold !px-8 !py-3">{{ $s['cta_primary'] }}</a>
            <a href="{{ auth()->check() ? route('catalogue') : route('login') }}" class="nx-btn !px-8 !py-3 !text-white" style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / .6)">{{ $s['cta_secondary'] }}</a>
        </div>
        <p class="relative mt-6 text-xs text-teal-100">{{ $s['trust'] }}</p>
    </div>
</section>
