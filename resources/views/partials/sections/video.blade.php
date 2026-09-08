@php
    /** Video embed (Section Builder §2). Parses a YouTube/Vimeo URL to a privacy-
     *  friendly embed; unknown hosts are ignored (never raw-embed arbitrary HTML). */
    $c = $config ?? [];
    $url = trim((string) ($c['url'] ?? ''));
    $embed = null;
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([\w-]{11})#i', $url, $m)) {
        $embed = 'https://www.youtube-nocookie.com/embed/'.$m[1];
    } elseif (preg_match('#vimeo\.com/(?:video/)?(\d+)#i', $url, $m)) {
        $embed = 'https://player.vimeo.com/video/'.$m[1];
    }
@endphp

@if ($embed)
    <section class="nx-sec-video py-14 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <div class="aspect-video overflow-hidden rounded-3xl border border-slate-200/70 shadow-sm dark:border-white/10">
                <iframe src="{{ $embed }}" title="{{ $c['caption'] ?? 'Video' }}" loading="lazy"
                        class="h-full w-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
            @if (! empty($c['caption']))
                <p class="mt-3 text-center text-sm text-slate-500 dark:text-slate-400">{{ $c['caption'] }}</p>
            @endif
        </div>
    </section>
@endif
