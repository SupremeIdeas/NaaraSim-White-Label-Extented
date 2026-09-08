<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — Blog'">
    <div class="mx-auto max-w-5xl px-4 py-16">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">The {{ \App\Support\BrandSettings::name() }} Blog</p>
            <h1 class="mt-3 font-display text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Travel smarter, stay connected</h1>
            <p class="mt-3 text-slate-600 dark:text-slate-300">Guides, tips and updates on eSIMs, numbers and travelling the world without roaming surprises.</p>
        </div>

        @if ($categories->isNotEmpty())
            <div class="mt-8 flex flex-wrap gap-2">
                <a href="{{ route('blog') }}" @class(['rounded-full px-3 py-1.5 text-sm font-medium transition', 'bg-primary text-white' => ! $activeCategory, 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#243352] dark:text-slate-300' => $activeCategory])>All</a>
                @foreach ($categories as $cat)
                    <a href="{{ route('blog', ['category' => $cat]) }}" @class(['rounded-full px-3 py-1.5 text-sm font-medium transition', 'bg-primary text-white' => $activeCategory === $cat, 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#243352] dark:text-slate-300' => $activeCategory !== $cat])>{{ $cat }}</a>
                @endforeach
            </div>
        @endif

        @if ($posts->isEmpty())
            <div class="mt-12 rounded-2xl border border-dashed border-slate-300 p-16 text-center text-slate-400 dark:border-[var(--brand-card-border-dark)] dark:text-slate-500">
                No posts yet — check back soon.
            </div>
        @else
            <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ route('blog.show', $post) }}" wire:key="post-{{ $post->id }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:shadow-lg dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                        <div class="aspect-[16/9] overflow-hidden bg-slate-100 dark:bg-[#243352]">
                            @if ($post->cover_image_url)
                                <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105">
                            @else
                                <div class="flex h-full items-center justify-center bg-gradient-to-br from-primary/15 to-accent/15 text-primary dark:text-teal-300">
                                    <x-icon name="file-text" class="h-8 w-8" />
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <span class="text-xs font-semibold uppercase tracking-wide text-accent-dark dark:text-accent">{{ $post->category }}</span>
                            <h2 class="mt-2 font-display text-lg font-bold leading-snug text-slate-900 dark:text-white">{{ $post->title }}</h2>
                            @if ($post->excerpt)
                                <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($post->excerpt, 110) }}</p>
                            @endif
                            <span class="mt-4 text-xs text-slate-400 dark:text-slate-500">{{ optional($post->published_at)->format('M j, Y') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </div>
</x-layouts.marketing>
