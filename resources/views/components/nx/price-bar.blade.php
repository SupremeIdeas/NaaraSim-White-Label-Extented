{{-- Sticky checkout footer: fees + total (+ local equivalent), then the CTA and a note passed as the slot. Money uses tabular figures. --}}
@props(['fees' => null, 'feesLabel' => null, 'payLabel' => null, 'total' => null, 'local' => null, 'row' => false, 'totalIsText' => false])
<div {{ $attributes->merge(['class' => 'ns-pricebar'.($row ? ' ns-pricebar--row' : '')]) }}>
    @if ($fees !== null || $total !== null)
        <div class="ns-price">
            @if ($fees !== null)<span>{{ $feesLabel }}<b style="font-size:18px;margin-top:6px">{{ $fees }}</b></span>@endif
            <span class="ns-price__right">{{ $payLabel }}<b @if ($totalIsText) class="ns-price__text" @endif>{{ $total }}</b>@if ($local)<em>{{ $local }}</em>@endif</span>
        </div>
    @endif
    {{ $slot }}
</div>
