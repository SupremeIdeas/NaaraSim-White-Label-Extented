{{-- Pricing teaser (CMS: home.pricing). Real plan tiers arrive with Module 29
     once provider APIs are live; this teaser stays honest until then. --}}
<section class="mx-auto max-w-4xl px-4 py-20 text-center" data-bg="light">
    <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] }}</p>
    <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
    <p data-reveal class="mx-auto mt-4 max-w-2xl leading-relaxed text-slate-600 dark:text-slate-300">{{ $s['text'] }}</p>

    <p data-reveal class="mt-8 inline-block rounded-full bg-primary/10 px-5 py-2 text-sm font-semibold text-primary dark:bg-primary/20 dark:text-teal-300">
        {{ $s['callout'] }}
    </p>

    <div data-reveal class="mt-8">
        <a href="{{ route('pricing') }}" class="nx-btn nx-btn--primary !px-8 !py-3">{{ $s['cta'] }}</a>
        <p class="mt-3 text-xs text-slate-400">{{ $s['trust'] }}</p>
    </div>
</section>
