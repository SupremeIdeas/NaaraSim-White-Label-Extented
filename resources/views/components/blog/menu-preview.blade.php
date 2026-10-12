{{-- "From the blog" block for the More menu (Engineering v3 8.2). Layout (grid | list | carousel) and count are admin settings
     (Admin > Blog reader). Cached query; any failure renders nothing, so the menu always opens. --}}
@php
    $items = [];
    $layout = 'carousel';
    try {
        $layout = (string) \App\Support\Blog\BlogReaderSettings::get('header_blog_layout', 'carousel');
        $items = \App\Support\Blog\RelatedPosts::latest((int) \App\Support\Blog\BlogReaderSettings::get('header_blog_count', 4));
    } catch (\Throwable) {
    }
@endphp
@if ($items !== [] && Route::has('blog'))
<section class="mb-4" aria-label="From the blog" data-blog-preview="{{ $layout }}">
    <div class="mb-2 flex items-center justify-between"><h3 class="text-sm font-semibold text-[rgb(var(--nx-text))]">From the blog</h3><a href="{{ route('blog') }}" wire:navigate @click="moreOpen = false" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))]">All articles</a></div>
    @if ($layout === 'carousel')
        <x-blog.carousel :items="$items" label="From the blog" source="menu" />
    @else
        <ul @class(['gap-2.5', 'grid grid-cols-2' => $layout === 'grid', 'space-y-2' => $layout === 'list'])>
            @foreach ($items as $it)
                <li>
                    <a href="{{ $it['url'] }}" wire:navigate @click="moreOpen = false" @class(['group flex overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]', 'flex-col h-full' => $layout === 'grid', 'items-center gap-3 p-2' => $layout === 'list'])>
                        <span @class(['block shrink-0 overflow-hidden bg-[rgb(var(--nx-surface-3))]', 'aspect-[16/9] w-full' => $layout === 'grid', 'h-16 w-20 rounded-xl' => $layout === 'list'])>
                            @if ($it['cover'])<img src="{{ $it['cover'] }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                            @else<span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#0D1B2A] to-[#0A6E6E] text-xl font-bold text-white/80" aria-hidden="true">{{ mb_strtoupper(mb_substr($it['category'] ?: $it['title'], 0, 1)) }}</span>@endif
                        </span>
                        <span @class(['block min-w-0', 'p-2.5' => $layout === 'grid'])><span class="line-clamp-2 text-[13px] font-semibold leading-snug text-[rgb(var(--nx-text))]">{{ $it['title'] }}</span><span class="mt-0.5 block text-[11px] text-[rgb(var(--nx-text-2))]">{{ $it['minutes'] }} min read</span></span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif
