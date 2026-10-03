{{-- Primary action. teal = instant utility; gold = commits wallet funds (one gold surface per screen, Prompt 20 §3). --}}
@props(['variant' => 'teal', 'icon' => null, 'loading' => null, 'type' => 'button'])
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'ns-cta'.($variant === 'gold' ? ' ns-cta--gold' : '')]) }}
    @if ($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif>
    @if ($icon)<x-nx.icon :name="$icon" />@endif
    {{ $slot }}
</button>
