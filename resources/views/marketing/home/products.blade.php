{{-- Products showcase (CMS: home.products). The section header stays
     admin-editable via the SiteEditor ($s); the product bodies are now the rich,
     admin-managed Naara product lines (ProductLineSettings) presented through the
     reusable storytelling carousel (BLUEPRINT-batch1-sections §3/§4) — each
     product a slide, its floating action button opening a deep 3-beat modal.
     Replaces the old shallow p1..p4 panels. --}}
@php($productSlides = \App\Support\ProductLineSettings::slides())
<x-ambient-glow class="px-4 py-20" data-bg="light">
    <div class="mx-auto w-full max-w-5xl">
        <div class="text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] }}</p>
            <h2 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
            @if (! empty($s['subtext']))
                <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-slate-600 dark:text-slate-300">{{ $s['subtext'] }}</p>
            @endif
        </div>

        <div class="mt-12">
            <x-storytelling-carousel :slides="$productSlides" section-key="product-lines" height="h-72 sm:h-[26rem]" />
        </div>
    </div>
</x-ambient-glow>
