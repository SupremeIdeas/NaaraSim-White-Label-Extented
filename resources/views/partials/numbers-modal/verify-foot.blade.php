@unless ($order)
    @php($payable = $modalPrice !== null ? ($useCredits ? $modalPrice - $creditQuote['usd'] : $modalPrice) : null)
    <x-nx.price-bar :fees-label="__('numbers.taxes_and_fees')" fees="$0.00" :pay-label="__('numbers.you_pay')" :total-is-text="$payable === null"
                    :total="$payable !== null ? '$'.number_format($payable, 2) : __('numbers.priced_at_reservation')">
        <x-nx.cta wire:click="order" loading="order">
            <span wire:loading.remove wire:target="order" class="ns-cta__label"><x-nx.icon name="shield" /> {{ __('numbers.verify.get_my_code') }}</span>
            <span wire:loading wire:target="order" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> {{ __('numbers.reserving') }}</span>
        </x-nx.cta>
        {{-- Refund guarantee, stated truthfully (Prompt 10 §1): the timeout comes from PollSmsOtpJob's own constant, never hardcoded. --}}
        <x-nx.note icon="info">{{ __('numbers.verify.refund_guarantee', ['minutes' => \App\Jobs\PollSmsOtpJob::TIMEOUT_MINUTES]) }}</x-nx.note>
    </x-nx.price-bar>
@endunless
