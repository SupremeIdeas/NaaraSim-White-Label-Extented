{{-- Share button (component-library batch 2 §4). Data-driven: the network
     targets come from SocialLinks::shareTargets() — only platforms the brand has
     actually linked AND that support a web share-intent. On mobile, the native
     share sheet (navigator.share) is offered first; a "Copy link" fallback is
     always present. No third-party script. Dark-mode aware.

     Props:
       url    — the page to share (defaults to the current URL)
       title  — share text (defaults to the app name)
       label  — trigger label (defaults to "Share") --}}
@props([
    'url' => null,
    'title' => null,
    'label' => 'Share',
])
@php
    $shareUrl = $url ?: url()->current();
    $shareTitle = $title ?: config('app.name', 'NaaraSim');
    $targets = \App\Support\SocialLinks::shareTargets($shareUrl, $shareTitle);
@endphp
<div x-data="{
        open: false,
        url: @js($shareUrl),
        title: @js($shareTitle),
        copied: false,
        canNativeShare: false,
        init() { this.canNativeShare = !!(navigator.share); },
        async nativeShare() {
            try { await navigator.share({ title: this.title, url: this.url }); this.open = false; }
            catch (e) { /* user cancelled — keep the menu open */ }
        },
        async copy() {
            try {
                await navigator.clipboard.writeText(this.url);
                this.copied = true;
                setTimeout(() => this.copied = false, 1800);
            } catch (e) {
                window.dispatchEvent(new CustomEvent('nx-toast', { detail: { type: 'error', message: 'Could not copy the link.' } }));
            }
        },
     }"
     x-on:keydown.escape.window="open = false"
     class="relative inline-block">
    <button type="button"
            x-on:click="open = !open"
            :aria-expanded="open"
            aria-haspopup="true"
            class="nx-btn nx-btn--ghost !px-3 !py-2">
        <x-icon name="share" class="h-4 w-4" />
        <span>{{ $label }}</span>
    </button>

    <div x-cloak x-show="open"
         x-transition.origin.top
         x-on:click.outside="open = false"
         class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]"
         role="menu">

        {{-- Native share sheet — mobile / supported browsers only. --}}
        <button type="button" x-show="canNativeShare" x-on:click="nativeShare()"
                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-white/5"
                role="menuitem">
            <x-icon name="share" class="h-4 w-4 text-primary dark:text-teal-300" />
            Share…
        </button>

        @foreach ($targets as $t)
            <a href="{{ $t['href'] }}" target="_blank" rel="noopener noreferrer"
               x-on:click="open = false"
               class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-white/5"
               role="menuitem">
                <x-service-icon :slug="$t['icon']" class="h-4 w-4" />
                {{ $t['label'] }}
            </a>
        @endforeach

        {{-- Copy link — always available. --}}
        <button type="button" x-on:click="copy()"
                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-white/5"
                role="menuitem">
            <x-icon name="copy" class="h-4 w-4 text-slate-500 dark:text-slate-400" />
            <span x-show="!copied">Copy link</span>
            <span x-show="copied" x-cloak class="inline-flex items-center gap-1 text-primary dark:text-teal-300"><x-icon name="check" class="h-3.5 w-3.5" /> Link copied</span>
        </button>
    </div>
</div>
