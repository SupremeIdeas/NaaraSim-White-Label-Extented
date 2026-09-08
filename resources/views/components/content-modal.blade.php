@props([
    'name',              // unique modal name (opened via $dispatch('open-modal', {name}))
    'title' => '',
    'gallery' => [],     // array of image URLs (2–5) — scrollable strip
    'blocks' => [],      // ordered [{heading, text}] rich content
    'ctaLabel' => null,
    'ctaUrl' => null,
])

{{-- Generic rich content modal (BLUEPRINT-batch1-sections §3/§4). A thin wrapper
     over the ONE modal engine (x-ui.modal), reused by every product line and the
     About page — deeper copy + a small image gallery for whatever the carousel
     is currently showing. --}}
<x-ui.modal :name="$name" :title="$title" max-width="2xl">
    @if (! empty($gallery))
        <div class="mb-5 flex snap-x gap-3 overflow-x-auto pb-2" style="-webkit-overflow-scrolling:touch;">
            @foreach ($gallery as $img)
                <img src="{{ $img }}" alt="" loading="lazy"
                     class="h-40 w-64 shrink-0 snap-start rounded-xl object-cover ring-1 ring-slate-200 dark:ring-white/10">
            @endforeach
        </div>
    @endif

    <div class="space-y-5">
        @foreach ($blocks as $block)
            <div>
                @if (! empty($block['heading']))
                    <h3 class="text-sm font-bold uppercase tracking-wide text-primary dark:text-teal-300">{{ $block['heading'] }}</h3>
                @endif
                @if (! empty($block['text']))
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $block['text'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($ctaLabel && $ctaUrl)
        <div class="mt-6">
            <a href="{{ $ctaUrl }}" wire:navigate
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                {{ $ctaLabel }} <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </div>
    @endif
</x-ui.modal>
