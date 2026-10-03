@if ($permanentAvailable)
    <x-nx.price-bar>
        <x-nx.cta wire:click="searchLine" loading="searchLine">
            <span wire:loading.remove wire:target="searchLine" class="ns-cta__label"><x-nx.icon name="search" /> {{ ! empty($lineNumbers) ? __('numbers.line.search_again') : __('numbers.line.search_available') }}</span>
            <span wire:loading wire:target="searchLine" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> {{ __('numbers.line.searching') }}</span>
        </x-nx.cta>
    </x-nx.price-bar>
@endif
