<x-layouts.marketing :title="$post->metaTitle().' — '.\App\Support\BrandSettings::name()"
                     :description="$post->metaDescription()" :og-image="$post->cover_image_url">
    {{-- Accent wash tied to this post's colour (Blog overhaul §5). --}}
    <div class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-96" aria-hidden="true"
         style="background: radial-gradient(90% 60% at 50% 0%, {{ $post->accentColor() }}22 0%, transparent 60%);"></div>
    <article class="mx-auto max-w-3xl px-4 py-16">
        <a href="{{ route('blog') }}" wire:navigate class="mb-6 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> All posts
        </a>

        <span class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $post->accentColor() }}">{{ $post->category }}</span>
        <h1 class="mt-2 font-display text-3xl font-bold leading-tight text-slate-900 sm:text-4xl dark:text-white">{{ $post->title }}</h1>
        <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">
            {{ optional($post->published_at)->format('F j, Y') }}@if ($post->author) · {{ $post->author->name }}@endif
        </p>

        @if ($post->cover_image_url)
            <img src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" class="mt-8 aspect-[16/9] w-full rounded-2xl object-cover">
        @endif

        @if ($post->excerpt)
            <p class="mt-8 text-lg leading-relaxed text-slate-700 dark:text-slate-200">{{ $post->excerpt }}</p>
        @endif

        <x-prose :body="$post->body" class="mt-6" />

        {{-- Reader actions: reactions + share (component-library batch 2 §4/§5). --}}
        <div class="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6 dark:border-[var(--brand-card-border-dark)]">
            <livewire:post-reactions :post="$post" :key="'react-'.$post->id" />
            <x-share-button :url="route('blog.show', $post)" :title="$post->title" />
        </div>
    </article>
</x-layouts.marketing>
