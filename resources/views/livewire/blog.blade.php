@php
    // Reuse the shared Section-Builder hero (images-reveal mode) for the 4-image
    // interchanging hero — one hero component across the platform, not a bespoke one.
    $heroImages = array_slice(array_values(array_filter((array) ($hero['images'] ?? []))), 0, 4);
    $heroConfig = [
        'preset' => 'showcase',
        'mode' => $heroImages ? 'images' : 'animation',
        'scheme' => 'dark',
        'align' => 'left',
        'eyebrow' => \App\Support\BrandSettings::name().' Blog',
        'headline' => $hero['title'] ?? 'The blog',
        'subheadline' => $hero['subtitle'] ?? '',
        'images' => $heroImages,
        'cta_primary_label' => '', 'cta_secondary_label' => '',
    ];
@endphp

<div x-data="nxBlogFeed()" x-init="init()">
    {{-- Scroll-tied accent background (Blog overhaul §5). Sits behind everything;
         its colour is lerped toward the post nearest the viewport centre. --}}
    <div class="nx-blog-bg" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-4 py-10 sm:py-14">
        {{-- Hero --}}
        @include('partials.sections.hero', ['config' => $heroConfig])

        {{-- Swelling "recents" carousel --}}
        @if ($featured->isNotEmpty())
            <div class="mt-10">
                <div class="mb-3 flex items-end justify-between">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Recents</h2>
                    <a href="{{ route('blog') }}" class="text-sm font-semibold text-primary hover:underline dark:text-teal-300">See all</a>
                </div>
                <div x-ref="recents" class="nx-blog-recents flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($featured as $post)
                        <a href="{{ route('blog.show', $post) }}" wire:navigate
                           class="nx-blog-card group relative aspect-[3/4] w-56 shrink-0 snap-center overflow-hidden rounded-3xl bg-slate-800 shadow-sm transition-transform duration-300">
                            @if ($post->cover_image_url)
                                <img src="{{ $post->cover_image_url }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                            @else
                                <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $post->accentColor() }}, #0D1B2A);"></div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-black/40"></div>
                            <div class="absolute inset-x-0 bottom-0 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ $post->category }}</span>
                                <h3 class="mt-0.5 text-base font-bold leading-tight text-white">{{ $post->title }}</h3>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Category filter --}}
        @if ($categories->isNotEmpty())
            <div class="mt-10 flex flex-wrap gap-2">
                <button type="button" wire:click="$set('category', null)" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium transition', 'bg-primary text-white' => ! $category, 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300' => $category])>All</button>
                @foreach ($categories as $cat)
                    <button type="button" wire:click="$set('category', @js($cat))" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium transition', 'bg-primary text-white' => $category === $cat, 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300' => $category !== $cat])>{{ $cat }}</button>
                @endforeach
            </div>
        @endif

        {{-- Main feed --}}
        @if ($posts->isEmpty())
            <div class="mt-12 rounded-2xl border border-dashed border-slate-300 p-16 text-center text-slate-400 dark:border-white/10 dark:text-slate-500">No posts yet — check back soon.</div>
        @else
            <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ route('blog.show', $post) }}" wire:navigate wire:key="post-{{ $post->id }}"
                       data-post data-accent="{{ $post->accentColor() }}"
                       class="group flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white transition hover:shadow-lg dark:border-white/10 dark:bg-slate-900/60">
                        <div class="aspect-[16/9] overflow-hidden bg-slate-100 dark:bg-white/5">
                            @if ($post->cover_image_url)
                                <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105">
                            @else
                                <div class="flex h-full items-center justify-center" style="background: linear-gradient(135deg, {{ $post->accentColor() }}22, {{ $post->accentColor() }}44);">
                                    <span class="h-3 w-3 rounded-full" style="background: {{ $post->accentColor() }}"></span>
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <span class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $post->accentColor() }}">{{ $post->category }}</span>
                            <h2 class="mt-2 font-display text-lg font-bold leading-snug text-slate-900 dark:text-white">{{ $post->title }}</h2>
                            @if ($post->excerpt)<p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($post->excerpt, 110) }}</p>@endif
                            <span class="mt-4 text-xs text-slate-400">{{ optional($post->published_at)->format('M j, Y') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Infinite scroll sentinel + graceful end --}}
            <div x-ref="sentinel" data-has-more="{{ $hasMore ? '1' : '0' }}" class="mt-10 flex justify-center">
                @if ($hasMore)
                    <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                            class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-6 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300">
                        <x-ui.spinner class="h-4 w-4" wire:loading wire:target="loadMore" /> Load more
                    </button>
                @else
                    <p class="text-sm text-slate-400">You're all caught up.</p>
                @endif
            </div>
        @endif
    </div>

    @once
        <style>
            .nx-blog-bg { position: fixed; inset: 0; z-index: -1; transition: background-color 0.8s ease; background-color: transparent; }
            .nx-blog-bg::after { content: ""; position: absolute; inset: 0; background: radial-gradient(120% 80% at 50% 0%, var(--blog-accent, transparent) 0%, transparent 55%); opacity: 0.14; transition: background 0.8s ease; }
            .nx-blog-card.is-focused { transform: scale(1.04); }
            @media (prefers-reduced-motion: reduce) { .nx-blog-bg, .nx-blog-bg::after, .nx-blog-card { transition: none; } }
        </style>
        <script>
            window.nxBlogFeed = function () {
                return {
                    init() {
                        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                        // Infinite scroll: load more when the sentinel nears view.
                        const io = new IntersectionObserver((entries) => {
                            entries.forEach((e) => {
                                if (e.isIntersecting && this.$refs.sentinel?.dataset.hasMore === '1') {
                                    this.$wire.loadMore();
                                }
                            });
                        }, { rootMargin: '400px' });
                        if (this.$refs.sentinel) io.observe(this.$refs.sentinel);
                        // Re-observe after Livewire morphs replace the sentinel.
                        Livewire.hook('morph.updated', () => { if (this.$refs.sentinel) io.observe(this.$refs.sentinel); });

                        // Scroll-tied accent tint from the post nearest the centre.
                        const bg = this.$el.querySelector('.nx-blog-bg');
                        const tint = new IntersectionObserver((entries) => {
                            entries.forEach((e) => {
                                if (e.isIntersecting && bg) {
                                    bg.style.setProperty('--blog-accent', e.target.dataset.accent || 'transparent');
                                }
                            });
                        }, { rootMargin: '-40% 0px -40% 0px' });
                        const observePosts = () => this.$el.querySelectorAll('[data-post]').forEach((p) => tint.observe(p));
                        observePosts();
                        Livewire.hook('morph.updated', observePosts);

                        // "Swell" the recents card nearest the row centre.
                        if (!reduce && this.$refs.recents) {
                            const swell = new IntersectionObserver((entries) => {
                                entries.forEach((e) => e.target.classList.toggle('is-focused', e.isIntersecting));
                            }, { root: this.$refs.recents, rootMargin: '0px -42% 0px -42%' });
                            this.$refs.recents.querySelectorAll('.nx-blog-card').forEach((c) => swell.observe(c));
                        }
                    },
                };
            };
        </script>
    @endonce
</div>
