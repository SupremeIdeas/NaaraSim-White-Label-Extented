{{-- "Keep reading" (Engineering v3 8.4). The carousel is ALWAYS rendered inline under the article (crawlable, no JS needed). When the admin
     allows it, a dismissible modal / bottom sheet shows the same posts once, after the reader has really read the article:
     sentinel visible AND scrolled >= X% AND dwelled >= Y% of the read time AND no other modal open; once per article per session,
     a 2 minute cooldown between articles, and a "Don't show this again" that sticks on the device. Never on arrival. --}}
@props(['post'])
@php
    use App\Support\Blog\{BlogReaderSettings, RelatedPosts};
    $s = BlogReaderSettings::all();
    $items = $s['keep_enabled'] ? RelatedPosts::for($post) : [];
    $minutes = max(1, (int) ceil(str_word_count(strip_tags((string) $post->body)) / 220));
@endphp
@if ($items !== [])
<section class="mt-12" aria-labelledby="kr-h-{{ $post->id }}">
    <h2 id="kr-h-{{ $post->id }}" class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Keep reading</h2>
    <x-blog.carousel :items="$items" label="Keep reading" source="inline" />
    <a href="{{ route('blog') }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-primary hover:underline">See all articles</a>
</section>

@if ($s['keep_popup'])
<div x-data="blogKeepReading({ post: {{ $post->id }}, minutes: {{ $minutes }}, scroll: {{ (int) $s['keep_scroll_pct'] }}, dwell: {{ (int) $s['keep_dwell_pct'] }} })" x-init="arm()" data-keep-reading>
    <div x-ref="sentinel" aria-hidden="true" class="h-px"></div>
    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-[80] flex items-end justify-center p-0 sm:items-center sm:p-4" data-keep-reading-root>
            <div class="absolute inset-0 bg-black/50" @click="dismiss()" aria-hidden="true"></div>
            <div x-show="open" x-trap.noscroll="open" role="dialog" aria-modal="true" aria-labelledby="kr-pop-{{ $post->id }}" @keydown.escape.window="open && dismiss()"
                 class="relative max-h-[85dvh] w-full max-w-3xl overflow-y-auto rounded-t-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-2xl sm:rounded-3xl">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="kr-pop-{{ $post->id }}" class="text-lg font-semibold text-[rgb(var(--nx-text))]">Keep reading</h2>
                    <button type="button" @click="dismiss()" class="rounded-full p-2 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]" aria-label="Close"><x-icon name="x" class="h-5 w-5" /></button>
                </div>
                <div class="mt-4" @blog-card-click.window="clicked()"><x-blog.carousel :items="$items" label="Keep reading" source="popup" /></div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <a href="{{ route('blog') }}" class="font-medium text-primary hover:underline">See all articles</a>
                    <span class="flex gap-4"><button type="button" @click="dismiss()" class="text-[rgb(var(--nx-text-2))] hover:underline">Maybe later</button><button type="button" @click="never()" class="text-[rgb(var(--nx-text-2))] hover:underline">Don't show this again</button></span>
                </div>
            </div>
        </div>
    </template>
</div>
@once
@push('head')
<script>
(function () { const reg = () => {
    Alpine.data('blogKeepReading', (cfg) => ({
        open: false, seen: false, startedAt: 0, timer: null,
        ss(k, v) { try { return v === undefined ? sessionStorage.getItem(k) : sessionStorage.setItem(k, v); } catch (e) { return null; } },
        ls(k, v) { try { return v === undefined ? localStorage.getItem(k) : localStorage.setItem(k, v); } catch (e) { return null; } },
        track(event) { try { const t = document.querySelector('meta[name=csrf-token]'); fetch(@js(route('blog.reader-event')), { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': t ? t.content : '', Accept: 'application/json' }, body: JSON.stringify({ event, post: cfg.post }) }); } catch (e) {} },
        arm() {
            if (this.ls('kr:never') === '1' || this.ss('kr:' + cfg.post) === '1') return;
            const last = parseInt(this.ls('kr:last') || '0', 10); if (last && Date.now() - last < 120000) return;
            this.startedAt = Date.now();
            const check = () => { if (this.open || this.seen) return; if (this.ready()) { this.show(); } };
                        this.timer = setInterval(check, 1500);
            addEventListener('scroll', check, { passive: true });
        },
        ready() {
            // "Read it": the end-of-article sentinel has come into (or passed through) the viewport AND the reader has been through >= X% of the article body.
            const art = this.$refs.sentinel.closest('article') || document.body; const ar = art.getBoundingClientRect();
            if (this.$refs.sentinel.getBoundingClientRect().top > innerHeight) return false;
            const seen = Math.min(ar.height, innerHeight - ar.top); if ((seen / Math.max(ar.height, 1)) * 100 < cfg.scroll) return false;
            const need = Math.max(8, cfg.minutes * 60 * (cfg.dwell / 100)); if ((Date.now() - this.startedAt) / 1000 < need) return false;
            return !this.otherModalOpen();
        },
        otherModalOpen() { const vis = (e) => { const c = getComputedStyle(e); return c.display !== 'none' && c.visibility !== 'hidden' && e.getClientRects().length > 0; }; return [...document.querySelectorAll('[aria-modal=true], [data-blog-share-root], .ns-modal')].some(e => !e.closest('[data-keep-reading-root]') && vis(e)); },
        show() { this.seen = true; this.open = true; this.ss('kr:' + cfg.post, '1'); this.ls('kr:last', String(Date.now())); this.track('readnext_shown'); clearInterval(this.timer); },
        clicked() { if (this.open) this.track('readnext_clicked'); },
        dismiss() { if (!this.open) return; this.open = false; this.track('readnext_dismissed'); },
        never() { this.ls('kr:never', '1'); this.open = false; this.track('readnext_disabled'); },
    }));
};
// A visit that arrives by in-app navigation comes after alpine:init has already fired, so register at once when Alpine is already running
if (window.Alpine) { reg(); } else { document.addEventListener('alpine:init', reg); }
})();
</script>
@endpush
@endonce
@endif
@endif
