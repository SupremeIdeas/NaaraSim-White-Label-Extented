{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div>
    @if ($alert)
        <div x-data="{ open: true }" x-show="open" x-cloak
             class="fixed inset-0 z-[100] flex items-center justify-center p-4"
             x-transition.opacity>
            {{-- Scrim --}}
            <div class="absolute inset-0 bg-black/70" @click="open = false; $wire.dismiss()"></div>

            {{-- Card --}}
            <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-[rgb(var(--nx-surface))] shadow-2xl"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                {{-- Gradient header --}}
                <div class="relative bg-gradient-to-br from-[rgb(var(--nx-cta-b))] via-[rgb(var(--nx-cta-a))] to-black/60 px-6 pb-8 pt-7 text-center text-white">
                    <button type="button" @click="open = false; $wire.dismiss()"
                        class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white/90 transition hover:bg-white/25" aria-label="Dismiss">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                    <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="bell" class="h-6 w-6" />
                    </span>
                    <h2 class="text-xl font-bold">{{ $alert->title }}</h2>
                </div>

                <div class="px-6 py-6">
                    @if ($alert->body)
                        <div class="nx-prose text-sm leading-relaxed text-[rgb(var(--nx-text-2))]">
                            {!! \App\Support\HtmlSanitizer::clean($alert->body) !!}
                        </div>
                    @endif

                    {{-- Coupon --}}
                    @if ($alert->coupon_code)
                        <div class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-dashed border-[rgb(var(--nx-teal)/0.4)] bg-[rgb(var(--nx-cta-b)/0.05)] px-4 py-3"
                             x-data="{ copied: false }">
                            <span class="font-mono text-sm font-bold tracking-wider text-[rgb(var(--nx-teal-ink))]">{{ $alert->coupon_code }}</span>
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
