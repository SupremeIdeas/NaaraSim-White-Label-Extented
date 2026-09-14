<div x-data="{ tab: 'mobile' }">
    {{-- Mobile / Toll-Free switcher --}}
    <div class="mb-4 inline-flex w-full rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-white/10 dark:bg-white/5">
        <button type="button" @click="tab = 'mobile'" :class="tab === 'mobile' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400'" class="flex-1 rounded-full px-4 py-1.5 text-sm font-semibold transition">{{ __('numbers.line.mobile_numbers') }}</button>
        <button type="button" @click="tab = 'toll'" :class="tab === 'toll' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400'" class="flex-1 rounded-full px-4 py-1.5 text-sm font-semibold transition">{{ __('numbers.line.toll_free') }}</button>
    </div>

    @unless ($permanentAvailable)
        <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-300 py-10 text-center dark:border-white/10">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-teal-500/15 dark:text-teal-300"><x-icon name="phone" class="h-6 w-6" gradient /></span>
            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ __('numbers.line.coming_soon_title') }}</p>
            <p class="max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ __('numbers.line.coming_soon_body') }}</p>
        </div>
    @else
        <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{!! __('numbers.line.intro', ['bold' => '<b>'.__('numbers.line.intro_bold').'</b>']) !!}</p>

        <div class="space-y-3">
            <button type="button" wire:click="pickCountry"
                    class="flex w-full items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left transition hover:border-primary/40 dark:border-white/10 dark:bg-white/5">
                <span class="flex min-w-0 items-center gap-3">
                    <x-country-flag :country="$country" class="h-6 w-8 shrink-0" />
                    <span class="min-w-0">
                        <span class="block text-[11px] uppercase tracking-wide text-slate-400">{{ __('numbers.country_label') }}</span>
                        <span class="block truncate font-semibold text-slate-900 dark:text-white">{{ $countryName }}</span>
                    </span>
                </span>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400" />
            </button>

            {{-- Vanity search (PermanentNumberRouter::search — digits + position). --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                <label class="mb-1.5 block text-[11px] uppercase tracking-wide text-slate-400">{{ __('numbers.line.vanity_label') }}</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="text" wire:model="vanity" inputmode="numeric" placeholder="{{ __('numbers.line.vanity_placeholder') }}"
                           class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                </div>
            </div>
        </div>

        @if ($lineDone)
            <div class="mt-4 rounded-2xl border border-green-200 bg-green-50 p-4 text-center dark:border-green-900/50 dark:bg-green-950/30">
                <x-icon name="badge-check" class="mx-auto mb-1 h-7 w-7 text-green-600 dark:text-green-300" />
                <p class="font-bold text-slate-900 dark:text-white">{{ __('numbers.line.active_title') }}</p>
                <p class="mt-0.5 select-all font-mono text-lg font-bold text-primary dark:text-teal-300">{{ $lineDone }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('numbers.line.active_hint') }}</p>
            </div>
        @endif

        @if ($error)
            <p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</p>
        @endif

        {{-- Results: number + monthly price + Get this number (provider never shown). --}}
        @if (! empty($lineNumbers))
            <div class="mt-4 space-y-2">
                @foreach ($lineNumbers as $n)
                    <div wire:key="line-{{ $n['number'] }}" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm font-bold text-slate-900 dark:text-white">{{ $n['number'] }}</p>
                            <p class="text-[11px] text-slate-400">{{ $n['locality'] ?: __('numbers.line.mobile_fallback') }} · ${{ number_format($n['monthly_retail'], 2) }}/mo</p>
                        </div>
                        <button type="button" wire:click="getLine('{{ $n['number'] }}')" wire:loading.attr="disabled" wire:target="getLine('{{ $n['number'] }}')"
                                class="shrink-0 rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                            <span wire:loading.remove wire:target="getLine('{{ $n['number'] }}')">{{ __('numbers.line.get_this_number') }}</span>
                            <span wire:loading wire:target="getLine('{{ $n['number'] }}')" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3.5 w-3.5" /> …</span>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex items-start gap-2 rounded-xl bg-primary/5 p-3 text-xs text-slate-500 dark:bg-teal-500/5 dark:text-slate-400">
            <x-icon name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-primary dark:text-teal-300" />
            <span>{{ __('numbers.line.monthly_note') }}</span>
        </div>

        <div class="sticky bottom-0 -mx-5 mt-5 border-t border-slate-100 bg-white px-5 pt-4 dark:border-white/10 dark:bg-[#0D1B2A]">
            <button type="button" wire:click="searchLine" wire:loading.attr="disabled" wire:target="searchLine"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="searchLine"><x-icon name="search" class="mr-1 inline h-4 w-4" /> {{ ! empty($lineNumbers) ? __('numbers.line.search_again') : __('numbers.line.search_available') }}</span>
                <span wire:loading wire:target="searchLine" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> {{ __('numbers.line.searching') }}</span>
            </button>
        </div>
    @endunless
</div>
