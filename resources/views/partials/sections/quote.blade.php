@php
    /** Premium pull-quote (Section Builder §2). Three admin-picked treatments. */
    $c = $config ?? [];
    $q = trim((string) ($c['quote'] ?? ''));
    $treatment = $c['treatment'] ?? 'gradient';
    $wrap = match ($treatment) {
        'dark' => 'bg-navy text-white',
        'minimal' => 'bg-transparent text-slate-900 dark:text-white',
        default => 'text-white nx-quote-gradient',
    };
@endphp

@if ($q !== '')
    <section class="nx-sec-quote {{ $wrap }} py-16 sm:py-24">
        <figure class="mx-auto max-w-3xl px-6 text-center">
            <blockquote class="text-2xl font-bold leading-snug sm:text-4xl">“{{ $q }}”</blockquote>
            @if (! empty($c['attribution']))
                <figcaption class="mt-5 text-sm font-medium opacity-80">— {{ $c['attribution'] }}</figcaption>
            @endif
        </figure>
    </section>
    @once
        <style>
            .nx-quote-gradient { background: linear-gradient(135deg, rgb(var(--brand-primary)), rgb(var(--brand-primary-dark)) 55%, rgb(var(--brand-navy))); }
        </style>
    @endonce
@endif
