{{-- Statement band (CMS: home.ticker): a slow, oversized ticker of what the platform does, then four proof numbers. Editable: the phrases and the numbers. --}}
@php
    $phrases = array_values(array_filter(array_map('trim', preg_split('/[·|\n]/u', (string) ($s['items'] ?? ''))), fn ($t) => ! (stripos($t, 'gift') !== false && \App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_GIFT_CARDS))));
@endphp
<section class="nx-ticker" data-bg="light" aria-label="{{ \App\Support\BrandSettings::name() }}">
    <div class="nx-ticker__rail" aria-hidden="true">
        <div class="nx-ticker__track">
            @foreach ([0, 1] as $copy)
                <ul>@foreach ($phrases as $ph)<li>{{ $ph }}</li>@endforeach</ul>
            @endforeach
        </div>
    </div>
    <dl class="mx-auto mt-10 grid max-w-5xl grid-cols-2 gap-px overflow-hidden rounded-3xl border border-slate-200 bg-slate-200 px-0 dark:border-white/10 dark:bg-white/10 md:grid-cols-4">
        @foreach (['stat_1', 'stat_2', 'stat_3', 'stat_4'] as $k)
            @php($parts = array_pad(array_map('trim', explode('|', (string) ($s[$k] ?? ''), 2)), 2, ''))
            <div class="bg-white px-5 py-6 text-center dark:bg-[var(--brand-card-dark)]" data-reveal>
                <dt class="sr-only">{{ $parts[1] }}</dt>
                <dd class="font-display text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $parts[0] }}</dd>
                <dd class="mt-1 text-xs font-medium uppercase tracking-widest text-slate-500 dark:text-slate-400">{{ $parts[1] }}</dd>
            </div>
        @endforeach
    </dl>
</section>
