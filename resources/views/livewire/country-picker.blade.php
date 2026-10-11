{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div>
    @if ($open)
        <div class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center"
             x-data="{ q: '' }" @keydown.escape.window="$wire.close()"
             role="dialog" aria-modal="true" aria-label="Choose a country">
            <div class="absolute inset-0 bg-black/60" wire:click="close"></div>

            <div class="relative flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-[rgb(var(--nx-surface))] shadow-2xl sm:rounded-3xl">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] px-5 py-4">
                    <h2 class="flex items-center gap-2 text-base font-bold text-[rgb(var(--nx-text))]">
                        <x-icon name="globe" class="h-5 w-5" gradient /> {{ $title ?? 'Browse by country' }}
                    </h2>
                    <button type="button" wire:click="close" aria-label="Close"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                {{-- Search (instant, client-side) --}}
                <div class="px-5 pt-4">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[rgb(var(--nx-text-2))]" />
                        <input type="text" x-model="q" placeholder="Search countries…" autofocus
                               class="w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] py-2.5 pl-9 pr-3 text-sm text-[rgb(var(--nx-text))] placeholder-[rgb(var(--nx-text-2))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]">
                    </div>
                </div>

                {{-- Options --}}
                <div class="mt-3 flex-1 overflow-y-auto px-3 pb-4">
                    {{-- All countries (clear filter) --}}
                    <button type="button" wire:click="clearPick"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-[rgb(var(--nx-surface-2))]"
                            x-show="q === ''">
                        <span class="flex h-6 w-8 items-center justify-center rounded-[3px] bg-[rgb(var(--nx-surface-3))]"><x-icon name="globe" class="h-4 w-4 text-[rgb(var(--nx-text-2))]" /></span>
                        <span class="text-sm font-semibold text-[rgb(var(--nx-text))]">All countries</span>
                    </button>

                    @forelse ($options as $opt)
                        <button type="button"
                                wire:click="pick('{{ $opt['code'] }}', @js($opt['name']))"
                                wire:key="cp-{{ $opt['code'] }}"
                                data-name="{{ Str::lower($opt['name']) }}"
                                data-code="{{ Str::lower($opt['code']) }}"
                                x-show="q === '' || $el.dataset.name.includes(q.toLowerCase().trim()) || $el.dataset.code.includes(q.toLowerCase().trim())"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-[rgb(var(--nx-surface-2))]">
                            <x-country-flag :country="$opt['code']" class="h-6 w-8 shrink-0" />
                            <span class="flex-1 truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $opt['name'] }}</span>
                            @isset($opt['count'])
                                <span class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">
                                    {{ $opt['count'] }} {{ Str::plural('plan', $opt['count']) }}
                                </span>
                            @elseif (array_key_exists('dial', $opt))
                                <span class="flex shrink-0 items-center gap-1.5">
                                    {{-- Prompt 10: a real, provider-outcome-backed success rate for this
                                         country's OTP lane — never shown below the min-sample threshold,
                                         so a thin badge here always means genuine confidence, not a guess. --}}
                                    @if (($opt['success'] ?? null) !== null)
                                        <span class="rounded-full bg-[rgb(var(--nx-ok)/0.12)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-ok))]">
                                            {{ round($opt['success'] * 100) }}% success
                                        </span>
                                    @endif
                                    @if (! empty($opt['dial']))
                                        <span class="font-mono text-xs font-semibold text-[rgb(var(--nx-text-2))]">{{ $opt['dial'] }}</span>
                                    @endif
                                </span>
                            @endisset
                        </button>
                    @empty
                        <p class="px-3 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No countries available yet.</p>
                    @endforelse

                    {{-- Empty search state (client-side) --}}
                    <template x-if="q !== ''">
                        <p class="hidden px-3 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]"
                           x-show="![...$root.querySelectorAll('[data-name]')].some(el => el.dataset.name.includes(q.toLowerCase().trim()) || el.dataset.code.includes(q.toLowerCase().trim()))"
                           x-cloak>
                            No country matches “<span x-text="q"></span>”.
                        </p>
                    </template>
                </div>
            </div>
        </div>
    @endif
</div>
