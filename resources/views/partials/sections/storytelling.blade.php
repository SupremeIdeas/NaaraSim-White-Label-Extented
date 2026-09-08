@php
    /** Storytelling section (BLUEPRINT-batch1-sections §7). A page-builder block
     *  that renders the reusable <x-storytelling-carousel> from admin-managed
     *  slides — the same component the homepage product lines and the About page
     *  use, now droppable into any custom page. A slide's optional "modal_body"
     *  becomes the deep content the floating action button opens. */
    $c = $config ?? [];
    $slides = collect((array) ($c['slides'] ?? []))
        ->filter(fn ($s) => is_array($s) && (filled($s['title'] ?? null) || filled($s['image'] ?? null)))
        ->map(fn ($s) => [
            'image' => $s['image'] ?? '',
            'eyebrow' => $s['eyebrow'] ?? '',
            'title' => $s['title'] ?? '',
            'body' => $s['body'] ?? '',
            'cta_label' => $s['cta_label'] ?? null,
            'cta_url' => \App\Support\PageSections::target($s['cta_target'] ?? ''),
            'modal_blocks' => filled($s['modal_body'] ?? null) ? [['heading' => '', 'text' => $s['modal_body']]] : [],
        ])->values()->all();
    $key = 'pb-story-'.substr(md5(json_encode($c)), 0, 8);
    $onDark = ($c['tone'] ?? 'auto') === 'on-dark';
@endphp

@if (! empty($slides))
    <section @class(['px-4 py-16', 'bg-navy text-slate-100' => $onDark])>
        <div class="mx-auto w-full max-w-5xl">
            @if (! empty($c['heading']))
                <div class="mb-8 text-center">
                    <h2 @class(['text-3xl font-bold sm:text-4xl', 'text-white' => $onDark, 'text-slate-900 dark:text-white' => ! $onDark])>{{ $c['heading'] }}</h2>
                    @if (! empty($c['subheading']))
                        <p @class(['mx-auto mt-3 max-w-2xl leading-relaxed', 'text-slate-200' => $onDark, 'text-slate-600 dark:text-slate-300' => ! $onDark])>{{ $c['subheading'] }}</p>
                    @endif
                </div>
            @endif
            <x-storytelling-carousel :slides="$slides" :section-key="$key" :tone="$c['tone'] ?? 'auto'" />
        </div>
    </section>
@endif
