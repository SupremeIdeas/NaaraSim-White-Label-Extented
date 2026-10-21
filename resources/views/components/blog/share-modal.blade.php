{{-- Blog share modal (Engineering v3 8.3). A centred dialog on every viewport (never a clipped popover): backdrop, article preview,
     copyable link, a grid of large channel tiles, native share where the browser has it. Channels, order and text come from admin
     (BlogReaderSettings) and config/blog_share.php. Without JavaScript the trigger is a plain link list (<noscript>).
     Props: post (App\Models\Post). Slot: optional custom trigger content. --}}
@props(['post', 'label' => 'Share'])
@php
    use App\Support\Blog\{BlogReaderSettings, ShareLinks};
    $canonical = route('blog.show', $post);
    $text = trim((string) BlogReaderSettings::get('share_text', '')) ?: $post->title;
    $tags = trim((string) BlogReaderSettings::get('share_hashtags', ''));
    if ($tags !== '') { $text .= ' '.$tags; }
    $links = ShareLinks::for($canonical, $post->title, $text, (string) $post->cover_image_url, (array) BlogReaderSettings::get('share_channels', []));
    $native = (bool) BlogReaderSettings::get('share_native', true);
    $uid = 'bsm'.$post->id;
@endphp
<div x-data="blogShare({ url: @js($canonical), title: @js($post->title), text: @js($text), post: {{ $post->id }}, native: {{ $native ? 'true' : 'false' }} })" @keydown.escape.window="close()" @blog-share-close.window="close()" class="inline-block">
    <button type="button" x-ref="trigger" @click="openIt()" aria-haspopup="dialog" :aria-expanded="open" class="nx-btn nx-btn--ghost !px-3 !py-2">
        <x-icon name="share" class="h-4 w-4" /><span>{{ $label }}</span>
    </button>
    <noscript>
        <ul class="mt-2 flex flex-wrap gap-3 text-sm">@foreach ($links as $l)<li><a href="{{ $l['href'] }}" rel="noopener noreferrer">{{ $l['label'] }}</a></li>@endforeach</ul>
    </noscript>

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity.duration.150ms data-blog-share-root
             class="fixed inset-0 z-[90] flex items-center justify-center p-4" role="presentation">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-sm" @click="close()" aria-hidden="true"></div>
            <div x-show="open" x-trap.noscroll="open" role="dialog" aria-modal="true" aria-labelledby="{{ $uid }}-t" data-blog-share-dialog
                 class="relative flex max-h-[85dvh] w-full max-w-md flex-col overflow-hidden rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-2xl motion-safe:transition motion-safe:duration-200"
                 x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between gap-3 border-b border-[rgb(var(--nx-line))] px-5 py-4">
                    <h2 id="{{ $uid }}-t" class="text-base font-semibold text-[rgb(var(--nx-text))]">Share this article</h2>
                    <button type="button" @click="close()" class="rounded-full p-2 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))] focus-visible:outline focus-visible:outline-2" aria-label="Close"><x-icon name="x" class="h-5 w-5" /></button>
                </div>
                <div class="overflow-y-auto px-5 py-4">
                    <div class="flex items-center gap-3 rounded-2xl bg-[rgb(var(--nx-surface-2))] p-3">
                        @if ($post->cover_image_url)<img src="{{ $post->cover_image_url }}" alt="" loading="lazy" decoding="async" class="h-14 w-14 shrink-0 rounded-xl object-cover">@endif
                        <div class="min-w-0"><p class="line-clamp-2 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $post->title }}</p><p class="mt-0.5 truncate text-xs text-[rgb(var(--nx-text-2))]">{{ \App\Support\BrandSettings::name() }}</p></div>
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <input type="text" readonly value="{{ $canonical }}" @focus="$event.target.select()" aria-label="Article link" class="min-w-0 flex-1 rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm text-[rgb(var(--nx-text))]">
                        <button type="button" @click="copy()" class="nx-btn nx-btn--primary shrink-0 !px-4 !py-2.5" :class="copied && 'is-done'">
                            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak class="inline-flex items-center gap-1"><x-icon name="check" class="h-4 w-4" /> Copied</span>
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2.5 sm:grid-cols-4">
                        @foreach ($links as $l)
                            <a href="{{ $l['href'] }}" @if ($l['popup']) @click.prevent="go($event, '{{ $l['key'] }}')" @else @click="track('share_click', '{{ $l['key'] }}')" @endif target="_blank" rel="noopener noreferrer"
                               class="flex min-h-[72px] flex-col items-center justify-center gap-1.5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-2 text-center text-xs font-medium text-[rgb(var(--nx-text))] transition hover:bg-[rgb(var(--nx-surface-3))] focus-visible:outline focus-visible:outline-2">
                                @if ($l['icon'] === 'mail')<x-icon name="mail" class="h-6 w-6" />@else<x-service-icon :slug="$l['icon']" class="h-6 w-6" />@endif
                                <span>{{ $l['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
                <div x-show="canNative" x-cloak class="border-t border-[rgb(var(--nx-line))] px-5 py-3">
                    <button type="button" @click="nativeShare()" class="w-full rounded-xl py-2 text-sm font-medium text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-surface-3))]">More options...</button>
                </div>
            </div>
        </div>
    </template>
</div>
@once
@push('head')
<script>
(function () { const reg = () => {
    Alpine.data('blogShare', (cfg) => ({
        open: false, copied: false, canNative: false,
        init() { this.canNative = !!(cfg.native && navigator.share); this._nav = () => { this.open = false; }; document.addEventListener('livewire:navigate', this._nav); },
        destroy() { document.removeEventListener('livewire:navigate', this._nav); },
        openIt() { this.open = true; this.track('share_open'); },
        close() { if (!this.open) return; this.open = false; this.$nextTick(() => this.$refs.trigger && this.$refs.trigger.focus()); },
        track(event, channel) { try { const b = JSON.stringify({ event, channel: channel || '', post: cfg.post }); const u = @js(route('blog.reader-event')); const t = document.querySelector('meta[name=csrf-token]'); navigator.sendBeacon ? fetch(u, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': t ? t.content : '', Accept: 'application/json' }, body: b }) : null; } catch (e) {} },
        go(ev, key) { this.track('share_click', key); const w = window.open(ev.currentTarget.href, 'share', 'noopener,noreferrer,width=600,height=640'); if (!w) location.href = ev.currentTarget.href; },
        async copy() { try { await navigator.clipboard.writeText(cfg.url); this.copied = true; this.track('share_copy', 'copy'); window.dispatchEvent(new CustomEvent('nx-toast', { detail: { type: 'success', message: 'Link copied' } })); setTimeout(() => this.copied = false, 1800); } catch (e) { window.dispatchEvent(new CustomEvent('nx-toast', { detail: { type: 'error', message: 'Could not copy the link.' } })); } },
        async nativeShare() { try { await navigator.share({ title: cfg.title, text: cfg.text, url: cfg.url }); this.track('share_click', 'native'); this.close(); } catch (e) {} },
    }));
};
// A visit that arrives by in-app navigation comes after alpine:init has already fired, so register at once when Alpine is already running
if (window.Alpine) { reg(); } else { document.addEventListener('alpine:init', reg); }
})();
</script>
@endpush
@endonce
