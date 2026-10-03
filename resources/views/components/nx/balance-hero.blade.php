{{-- Balance / credits hero (the parent container of a wallet widget): the saturated accent card with a big tabular amount (wallet, rewards, home). Pass `href` to make the
     whole card a link; the default slot holds the trailing action (a pill or button); the `footer` slot houses the child components (sub-balance row, quick actions) INSIDE the card so every skin treats the whole widget as one premium surface. --}}
@props(['label' => null, 'amount' => null, 'sub' => null, 'href' => null, 'icon' => null])
@php($el = $href ? 'a' : 'div')
<{{ $el }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'ns-card ns-card--primary ns-balance']) }}>
    <i class="ns-deco" aria-hidden="true"></i>
    <span class="ns-balance__row">
        <span class="ns-balance__text">
            <small style="opacity:.9;display:flex;align-items:center;gap:6px">@isset($mark){{ $mark }}@elseif ($icon)<x-nx.icon :name="$icon" />@endif{{ $label }}</small>
            <span class="ns-amount">@isset($amountMark)<span class="ns-amount__mark">{{ $amountMark }}</span>@endisset{{ $amount }}</span>
            @if ($sub)<small style="opacity:.9">{{ $sub }}</small>@endif
        </span>
        @if (trim((string) $slot) !== '')<span class="ns-balance__action">{{ $slot }}</span>@endif
    </span>
    @isset($footer)<div class="ns-balance__footer">{{ $footer }}</div>@endisset
</{{ $el }}>
