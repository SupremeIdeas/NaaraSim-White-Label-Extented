{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div>
    @if ($alert)
        <div x-data="{ open: true }" x-show="open" x-cloak
             class="fixed inset-0 z-[100] flex items-end justify-center p-3 sm:items-center sm:p-4" style="padding-bottom:max(.75rem,env(safe-area-inset-bottom))" role="dialog" aria-modal="true" aria-label="{{ $alert->title }}"
             x-transition.opacity>
            {{-- Scrim --}}
            <div class="absolute inset-0 bg-black/70" @click="open = false; $wire.dismiss()"></div>

            {{-- Card --}}
            <div class="relative flex max-h-[calc(100dvh-1.5rem)] w-full min-w-0 max-w-md flex-col overflow-hidden rounded-3xl bg-[rgb(var(--nx-surface))] shadow-2xl sm:max-h-[90dvh]"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                {{-- Gradient header --}}
                <div class="relative shrink-0 bg-gradient-to-br from-[rgb(var(--nx-cta-b))] via-[rgb(var(--nx-cta-a))] to-black/60 px-12 pb-5 pt-6 text-center text-white sm:px-6 sm:pb-8 sm:pt-7">
                    <button type="button" @click="open = false; $wire.dismiss()"
                        class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white/90 transition hover:bg-white/25" aria-label="Dismiss">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                    <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="bell" class="h-6 w-6" />
                    </span>
                    <h2 class="break-words text-lg font-bold leading-snug sm:text-xl">{{ $alert->title }}</h2>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain px-5 py-5 sm:px-6 sm:py-6">
                    @if ($alert->body)
                        <div class="nx-prose break-words [&_a]:break-all [&_iframe]:max-w-full [&_img]:h-auto [&_img]:max-w-full [&_pre]:overflow-x-auto [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto text-sm leading-relaxed text-[rgb(var(--nx-text-2))]">
                            {!! \App\Support\HtmlSanitizer::clean($alert->body) !!}
                        </div>
                    @endif

                    {{-- Coupon --}}
                    @if ($alert->coupon_code)
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-dashed border-[rgb(var(--nx-teal)/0.4)] bg-[rgb(var(--nx-cta-b)/0.05)] px-4 py-3"
                             x-data="{ copied: false }">
                            <span class="min-w-0 break-all font-mono text-sm font-bold tracking-wider text-[rgb(var(--nx-teal-ink))]">{{ $alert->coupon_code }}</span>
                            <button type="button"
                                @click="navigator.clipboard.writeText('{{ $alert->coupon_code }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">
                                <x-icon name="copy" class="h-3.5 w-3.5" />
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="mt-6 flex flex-col gap-2">
                        @if ($alert->cta_label && $alert->cta_url)
                            <a href="{{ $alert->cta_url }}" @click="$wire.dismiss()"
                               @if (\Illuminate\Support\Str::startsWith($alert->cta_url, 'http')) target="_blank" rel="noopener" @endif
                               class="w-full rounded-full bg-[rgb(var(--nx-cta-b))] px-6 py-3 text-center text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">
                                {{ $alert->cta_label }}
                            </a>
                        @endif
                        <button type="button" @click="open = false; $wire.dismiss()"
                            class="w-full rounded-full px-6 py-2.5 text-center text-sm font-medium text-[rgb(var(--nx-text-2))] transition hover:text-[rgb(var(--nx-text))]">
                            {{ $alert->cta_label ? 'No thanks' : 'Got it' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
