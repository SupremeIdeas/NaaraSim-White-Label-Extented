@php
    /** Two-column section (Section Builder §2) — image + copy, side admin-picked.
     *  Graceful no-image fallback: with no image set it collapses to a clean,
     *  centered single-column text block (never a broken slot). */
    $c = $config ?? [];
    $image = trim((string) ($c['image'] ?? ''));
    $side = ($c['image_side'] ?? 'right') === 'left' ? 'left' : 'right';
    $bg = $c['bg'] ?? 'transparent';
    $ctaLabel = trim((string) ($c['cta_label'] ?? ''));
    $cta = \App\Support\PageSections::target($c['cta_target'] ?? '');
    $bgClass = match ($bg) {
        'tint' => 'bg-primary/5 dark:bg-primary/10',
        'dark' => 'bg-slate-900 text-white dark:bg-slate-950',
        default => '',
    };
@endphp

<section class="nx-sec-twocol {{ $bgClass }} py-14 sm:py-20">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="grid items-center gap-8 sm:gap-12 {{ $image ? 'lg:grid-cols-2' : 'max-w-2xl mx-auto text-center' }}">
            {{-- Copy --}}
            <div class="{{ $image && $side === 'left' ? 'lg:order-2' : '' }}">
                @if (! empty($c['eyebrow']))
                    <span class="mb-3 inline-block text-sm font-semibold uppercase tracking-wide text-primary dark:text-teal-300">{{ $c['eyebrow'] }}</span>
                @endif
                <h2 class="text-2xl font-bold leading-tight text-slate-900 dark:text-white sm:text-3xl {{ $bg === 'dark' ? 'text-white' : '' }}">{{ $c['headline'] ?? '' }}</h2>
                @if (! empty($c['body']))
                    <p class="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-300 {{ $bg === 'dark' ? 'text-slate-200' : '' }}">{{ $c['body'] }}</p>
                @endif
                @if ($ctaLabel !== '')
                    <a href="{{ $cta ?: '#' }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        {{ $ctaLabel }}
                        <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                @endif
            </div>

            {{-- Image (only when set) --}}
            @if ($image)
                <div class="{{ $side === 'left' ? 'lg:order-1' : '' }}">
                    <img src="{{ $image }}" alt="" loading="lazy" decoding="async"
                         class="w-full rounded-3xl border border-slate-200/60 object-cover shadow-sm dark:border-white/10">
                </div>
            @endif
        </div>
    </div>
</section>
