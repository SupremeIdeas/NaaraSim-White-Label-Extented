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
        <div style="margin-top:24px" @if ($order->status === 'waiting') wire:poll.3s @endif>
        <x-nx.page>
            <div class="ns-card ns-card--rent ns-ring ns-full" style="padding:16px">
                <span class="ns-card__row" style="justify-content:space-between;flex-wrap:wrap">
                    <span class="ns-card__text">
                        <small class="ns-small">{{ __('numbers.your_number') }}</small>
                        <b class="ns-result__num" style="font-size:20px">{{ $order->phone_number }}</b>
                    </span>
                    <span style="text-align:right">
                        @if ($order->otp_code)
                            <small class="ns-small">{{ __('numbers.code') }}</small>
                            <b class="ns-result__code">{{ $order->otp_code }}</b>
                        @else
                            <span class="ns-result__wait" style="margin-top:0"><x-ui.spinner class="h-4 w-4" /> {{ __('numbers.waiting_for_code') }}</span>
                        @endif
                    </span>
                </span>
                <span class="ns-done__actions" style="margin-top:14px;flex-wrap:wrap">
                    <button type="button" class="ns-btn" wire:click="reset_" wire:loading.attr="disabled" wire:target="reset_">{{ __('numbers.done') }}</button>
                    {{-- §6.1: send an SMS from this line without needing a saved contact. --}}
                    <button type="button" class="ns-btn" @click="$dispatch('open-send-message', { to: '', name: '' })"><x-nx.icon name="chat" /> {{ __('numbers.send_sms') }}</button>
                    <a href="{{ route('dashboard') }}" wire:navigate class="ns-btn ns-btn--solid">{{ __('numbers.view_on_dashboard') }}</a>
                </span>
            </div>
        </x-nx.page>
        </div>
    @endif

    {{-- Product modals (pop like the mobile sheet) + the shared pickers. --}}
    @include('partials.numbers-modals', ['pageMode' => $pageMode])
    <livewire:country-picker />
    <livewire:service-picker />
    {{-- Send-message modal host (§6.1) — reachable from the active-line card. --}}
    @livewire('send-message')


</div>
