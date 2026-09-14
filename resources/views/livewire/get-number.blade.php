<div class="mx-auto max-w-3xl">
    {{-- NaaraSim mark now lives in the header (App\Support\BrandContext) — this
         is a NaaraSim number surface, so the header already wears it. --}}

    {{-- Numbers top region (Theme Batch 2 §2) — HEADER/HERO REFLOW ONLY. The
         product modals + active-order surface below are identical in every theme;
         only the order of the hero (Numbers V6 §0) and the six-card bento grid
         (Numbers V6 §1) changes. variant-a = hero then bento (baseline);
         variant-b = bento then hero (action-first personas). Defaults to
         variant-a. Both partials are unchanged. --}}
    {{-- Admin-chosen "dedicated page" mode (owner request, 2026-09-08) for a
         normally-modal card (verify/rent/line): while that card's flow is
         open, this IS the dedicated page — hide the hero + bento grid behind
         it, the same way the platform never shows the bento grid on top of
         numbers.dialer/numbers.contacts either. Closing (`$modal = ''`)
         naturally brings the grid back, no separate "back" plumbing needed. --}}
    @php($pageMode = $modal !== '' && \App\Support\NumbersBento::isPageMode($modal))
    @unless ($pageMode)
        @php($numbersVariant = \App\Support\ThemePreset::layoutVariant('numbers'))
        @if ($numbersVariant === 'variant-b')
            @include('partials.numbers-bento')
            @include('partials.numbers-hero')
        @else
            @include('partials.numbers-hero')
            @include('partials.numbers-bento')
        @endif
    @endunless

    {{-- Active order surfaced on the page when no modal is open, so an
         in-progress number/OTP stays visible after the modal is closed. The
         order is auth-scoped in the component (IDOR-safe). --}}
    @if ($order && $modal === '')
        <div class="mt-6 rounded-2xl border border-slate-200 nx-glass-tile p-5 shadow-sm dark:border-[var(--brand-card-border-dark)]"
             @if ($order->status === 'waiting') wire:poll.3s @endif>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('numbers.your_number') }}</p>
                    <p class="select-all font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $order->phone_number }}</p>
                </div>
                <div class="text-right">
                    @if ($order->otp_code)
                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('numbers.code') }}</p>
                        <p class="select-all font-mono text-2xl font-bold text-primary dark:text-teal-300">{{ $order->otp_code }}</p>
                    @else
                        <p class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400"><x-ui.spinner class="h-4 w-4" /> {{ __('numbers.waiting_for_code') }}</p>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="reset_" wire:loading.attr="disabled" wire:target="reset_" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">{{ __('numbers.done') }}</button>
                {{-- §6.1: send an SMS from this line without needing a saved contact. --}}
                <button type="button" @click="$dispatch('open-send-message', { to: '', name: '' })"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/10 dark:border-primary/40 dark:text-teal-300">
                    <x-icon name="message-circle" class="h-4 w-4" /> {{ __('numbers.send_sms') }}
                </button>
                <a href="{{ route('dashboard') }}" wire:navigate class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">{{ __('numbers.view_on_dashboard') }}</a>
            </div>
        </div>
    @endif

    {{-- Product modals (pop like the mobile sheet) + the shared pickers. --}}
    @include('partials.numbers-modals', ['pageMode' => $pageMode])
    <livewire:country-picker />
    <livewire:service-picker />
    {{-- Send-message modal host (§6.1) — reachable from the active-line card. --}}
    @livewire('send-message')


</div>
