@unless ($order)
    @php($payable = $modalPrice !== null ? ($useCredits ? $modalPrice - $creditQuote['usd'] : $modalPrice) : null)
    <x-nx.price-bar :fees-label="__('numbers.taxes_and_fees')" fees="$0.00" :pay-label="__('numbers.you_pay')" :total-is-text="$payable === null"
                    :total="$payable !== null ? '$'.number_format($payable, 2) : __('numbers.priced_at_reservation')"
                    :local="$payable !== null && $isUsRental && isset($rentalDurations[$rentalDuration]) ? '('.$rentalDurations[$rentalDuration].')' : null">
        <x-nx.cta variant="gold" wire:click="order" loading="order">
            <span wire:loading.remove wire:target="order" class="ns-cta__label"><x-nx.icon name="hash" /> {{ __('numbers.rent.rent_this_number') }}</span>
            <span wire:loading wire:target="order" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> {{ __('numbers.reserving') }}</span>
        </x-nx.cta>
        <x-nx.note icon="lock">{{ __('numbers.rent.wallet_note') }}</x-nx.note>
    </x-nx.price-bar>
@endunless
