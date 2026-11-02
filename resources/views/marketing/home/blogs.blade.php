{{-- Recent blog posts (owner request, 2026-09-21 — "our homepage still
     needs recent blogs"). Self-contained: queries the same `Post::published()`
     scope the public blog index and Help Center hub already use, no new
     admin config needed — publishing a post is what makes it appear here.
     Same cover-image/accent-gradient fallback as the blog index (BUILD-30),
     so a post with no cover yet still reads as a deliberate design, not a
     blank box. --}}
@php
    $recentPosts = \Illuminate\Support\Facades\Schema::hasTable('posts')
        ? \App\Models\Post::published()->orderByDesc('published_at')->limit(3)->get()
        : collect();
@endphp
@if ($recentPosts->isNotEmpty())
<section class="mx-auto max-w-6xl px-4 py-20" data-bg="light">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] ?? '' }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] ?? '' }}</h2>
        </div>
        <a href="{{ route('blog') }}" wire:navigate data-reveal class="hidden items-center gap-1 text-sm font-semibold text-primary hover:underline sm:inline-flex dark:text-teal-300">
            {{ $s['cta_label'] ?? '' }} <x-icon name="arrow-up-right" class="h-3.5 w-3.5" />
        </a>
    </div>
    <div class="mt-10 grid gap-6 sm:grid-cols-3">
        @foreach ($recentPosts as $i => $post)
            <a href="{{ route('blog.show', $post) }}" wire:navigate data-reveal style="--reveal-delay: {{ $i * 0.08 }}s"
               class="group flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-xl dark:border-white/10 dark:bg-white/5">
                <div class="relative aspect-[1200/896] w-full overflow-hidden">
                    @if ($post->cover_image_url)
                        <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" loading="lazy" decoding="async"
                             class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @else
                        <div class="flex h-full items-center justify-center" style="background: linear-gradient(135deg, {{ $post->accentColor() }}22, {{ $post->accentColor() }}55);">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white/80 shadow-sm" style="color: {{ $post->accentColor() }}">
                                <x-icon name="file-text" class="h-5 w-5" />
                            </span>
                        </div>
                    @endif
                </div>
                <div class="flex flex-1 flex-col p-5">
                    <span class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $post->accentColor() }}">{{ $post->category }}</span>
                    <h3 class="mt-2 font-bold leading-snug text-slate-900 group-hover:text-primary dark:text-white dark:group-hover:text-teal-300">{{ $post->title }}</h3>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($post->excerpt, 100) }}</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-primary dark:text-teal-300">
                        {{ $s['card_cta'] ?? '' }} <x-icon name="arrow-up-right" class="h-3.5 w-3.5" />
                    </span>
                </div>
            </a>
        @endforeach
    </div>
    <a href="{{ route('blog') }}" wire:navigate data-reveal class="mt-8 flex items-center justify-center gap-1 text-sm font-semibold text-primary hover:underline sm:hidden dark:text-teal-300">
        {{ $s['cta_label'] ?? '' }} <x-icon name="arrow-up-right" class="h-3.5 w-3.5" />
    </a>
</section>
@endif
