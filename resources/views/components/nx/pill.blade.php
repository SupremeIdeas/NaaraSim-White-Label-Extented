{{-- Status pill. variant: best | rec | gold | out | (none = neutral). Gold is money/commit only (Popular, Premium outline). --}}
@props(['variant' => null])
<span {{ $attributes->merge(['class' => 'ns-pill'.($variant ? ' ns-pill--'.$variant : '')]) }}>{{ $slot }}</span>
