{{-- Selectable list row (network / operator). selected | auto (gold glow, "best network") | disabled (out of stock). `pills` slot
     sits after the label, `trailing` slot is the right-hand value (price or stats). --}}
@props(['icon' => 'bars', 'selected' => false, 'auto' => false, 'disabled' => false, 'chevron' => true])
<button type="button" @disabled($disabled)
    {{ $attributes->merge(['class' => 'ns-row'.($auto ? ' ns-row--auto' : ' ns-ring').($selected && ! $auto ? ' is-selected' : '').($selected && $auto ? ' is-selected' : '').($disabled ? ' ns-row--no' : '')]) }}
    aria-pressed="{{ $selected ? 'true' : 'false' }}">
    <x-nx.icon :name="$icon" />
    <span class="ns-row__label">{{ $slot }}</span>
    {{ $pills ?? '' }}
    @isset($trailing)<span class="ns-row__price">{{ $trailing }}</span>@endisset
    @if ($chevron && ! $disabled)<x-nx.icon name="chevron-right" class="ns-chevron" />@endif
</button>
